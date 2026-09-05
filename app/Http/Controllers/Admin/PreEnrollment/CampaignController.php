<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Enums\PreEnrollmentCampaignStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Family;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentForm;
use App\Notifications\PreEnrollmentCampaignOpenedNotification;
use App\Services\PreEnrollment\CampaignCreationService;
use App\Services\PreEnrollment\CampaignReadinessService;
use App\Services\PreEnrollment\CampaignStatsService;
use App\Services\PreEnrollment\DraftPrefillService;
use App\Services\PreEnrollment\Exceptions\DuplicateActiveCampaignException;
use App\Services\PreEnrollment\Exceptions\NoSourceAcademicYearException;
use App\Services\PreEnrollment\SelectionScreenService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    use ScopesCampaignToKinder;

    public function __construct(
        private readonly CampaignCreationService $campaignCreationService,
        private readonly CampaignStatsService $campaignStatsService,
        private readonly SelectionScreenService $selectionScreenService,
        private readonly CampaignReadinessService $campaignReadinessService,
        private readonly DraftPrefillService $draftPrefillService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaigns = PreEnrollmentCampaign::whereHas(
            'academicYear',
            fn ($query) => $query->where('kinder_id', $admin->kinder_id),
        )
            ->with('academicYear')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($campaigns->map(fn (PreEnrollmentCampaign $campaign) => $this->selectionScreenService->formatCampaign($campaign)));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'academicYearId' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('kinder_id', $admin->kinder_id),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'dueDate' => ['nullable', 'date'],
            'enforceDueDate' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $campaign = $this->campaignCreationService->create(
                academicYearId: $data['academicYearId'],
                createdByAdminUserId: $admin->id,
                name: $data['name'] ?? null,
                dueDate: isset($data['dueDate']) ? Carbon::parse($data['dueDate']) : null,
                enforceDueDate: $data['enforceDueDate'] ?? false,
                notes: $data['notes'] ?? null,
            );
        } catch (DuplicateActiveCampaignException) {
            throw ValidationException::withMessages([
                'academicYearId' => 'Ya existe una solicitud de pre-matrícula activa para este año.',
            ]);
        } catch (NoSourceAcademicYearException) {
            throw ValidationException::withMessages([
                'academicYearId' => 'No se encontró un año académico activo del cual proyectar los estudiantes.',
            ]);
        }

        $campaign->load('academicYear');

        return response()->json($this->selectionScreenService->formatCampaign($campaign), 201);
    }

    public function show(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        return response()->json([
            ...$this->selectionScreenService->formatCampaign($campaign),
            'stats' => $this->campaignStatsService->build($campaign),
        ]);
    }

    public function open(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        abort_unless($campaign->status === PreEnrollmentCampaignStatus::Draft, 422, 'Solo una solicitud en borrador puede abrirse.');

        $campaign->update(['status' => PreEnrollmentCampaignStatus::Open, 'opened_at' => now()]);

        $campaign->load('forms.student');

        $this->prefillDrafts($campaign);

        foreach ($this->campaignReadinessService->readyFamilies($campaign) as $family) {
            $this->sendCampaignOpenedNotification($campaign, $family);
        }

        return response()->json($this->selectionScreenService->formatCampaign($campaign));
    }

    public function close(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        abort_if(
            in_array($campaign->status, [PreEnrollmentCampaignStatus::Closed, PreEnrollmentCampaignStatus::Archived], true),
            422,
            'Esta solicitud ya está finalizada.',
        );

        PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('is_excluded', false)
            ->whereIn('status', [PreEnrollmentFormStatus::Pending, PreEnrollmentFormStatus::InProgress])
            ->update(['status' => PreEnrollmentFormStatus::NotSubmitted]);

        $campaign->update([
            'status' => PreEnrollmentCampaignStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $admin->id,
        ]);

        return response()->json($this->selectionScreenService->formatCampaign($campaign));
    }

    /**
     * "Confirm, don't retype" per §2: pre-fill every non-excluded form's draft_payload (and one
     * family-level draft per family) from whatever we already know, the moment the campaign
     * opens. Only touches forms/drafts that are still untouched (empty payload) so re-running
     * this (e.g. re-including a student later) never clobbers a family's in-progress answers.
     */
    private function prefillDrafts(PreEnrollmentCampaign $campaign): void
    {
        $forms = $campaign->forms->where('is_excluded', false);

        foreach ($forms as $form) {
            if (empty($form->draft_payload)) {
                $form->update(['draft_payload' => $this->draftPrefillService->prefillFormDraft($form)]);
            }
        }

        foreach ($forms->pluck('family_id')->unique() as $familyId) {
            $draft = PreEnrollmentFamilyDraft::firstOrCreate(
                ['campaign_id' => $campaign->id, 'family_id' => $familyId],
                ['draft_payload' => []],
            );

            if (empty($draft->draft_payload)) {
                $draft->update(['draft_payload' => $this->draftPrefillService->prefillFamilyDraft($draft->family)]);
            }
        }
    }

    /**
     * One notification instance per family, routed to every status=true guardian email (or
     * family.user as a fallback, per §9.2) — mirrors the anonymous-routing technique already
     * used by FamilyPasswordController::forgot(), since Family isn't Notifiable.
     */
    private function sendCampaignOpenedNotification(PreEnrollmentCampaign $campaign, Family $family): void
    {
        $emails = $family->guardians
            ->where('status', true)
            ->pluck('email')
            ->filter()
            ->unique();

        // §9.2's fallback assumes `user` can double as a contact address, which only holds when
        // it happens to be email-shaped (it's a login username otherwise, e.g. seeded "rodmor") —
        // guard so a non-email `user` never gets handed to the mailer as a recipient.
        if ($emails->isEmpty() && filter_var($family->user, FILTER_VALIDATE_EMAIL)) {
            $emails = collect([$family->user]);
        }

        foreach ($emails as $email) {
            Notification::route('mail', $email)
                ->notify(new PreEnrollmentCampaignOpenedNotification($campaign, $family));
        }
    }
}
