<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Enums\ActorType;
use App\Enums\PreEnrollmentFormStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentForm;
use App\Services\PreEnrollment\DraftPayloadMerger;
use App\Services\PreEnrollment\Exceptions\FormNotSubmittedException;
use App\Services\PreEnrollment\FieldChangeRecorder;
use App\Services\PreEnrollment\FormApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The family-granular submission surface: approval, reopening, and director corrections all
 * operate on every one of a family's included children at once, never on a single child — a
 * family's submission is either fully approved or not, so its children are never left half-live.
 */
class SubmissionController extends Controller
{
    use ScopesCampaignToKinder;

    public function __construct(
        private readonly FieldChangeRecorder $fieldChangeRecorder,
        private readonly FormApprovalService $formApprovalService,
    ) {}

    /**
     * Monitoring dashboard: searchable/filterable per-family list plus campaign-wide counts.
     */
    public function index(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);

        $filters = $request->validate([
            'search' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['not_started', 'in_progress', 'submitted', 'approved'])],
        ]);

        $formsByFamily = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('is_excluded', false)
            ->with('family')
            ->get()
            ->groupBy('family_id');

        $drafts = PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)->get()->keyBy('family_id');

        $rows = $formsByFamily->map(function (Collection $formsForFamily, int $familyId) use ($drafts) {
            $family = $formsForFamily->first()->family;
            $draft = $drafts->get($familyId) ?? new PreEnrollmentFamilyDraft(['draft_payload' => []]);

            return [
                'familyId' => $family->id,
                'familyName' => trim("{$family->last_name_one} {$family->last_name_two}"),
                'status' => $draft->statusGiven($formsForFamily),
                'studentsCount' => $formsForFamily->count(),
                'submittedAt' => $draft->submitted_at?->toIso8601String(),
                'approvedAt' => $draft->approved_at?->toIso8601String(),
            ];
        })->values();

        $counts = [
            'total' => $rows->count(),
            'submitted' => $rows->whereIn('status', ['submitted', 'approved'])->count(),
            'approved' => $rows->where('status', 'approved')->count(),
        ];

        $filtered = $rows
            ->when(isset($filters['search']), fn (Collection $r) => $r->filter(
                fn (array $row) => str_contains(mb_strtolower($row['familyName']), mb_strtolower($filters['search'])),
            ))
            ->when(isset($filters['status']), fn (Collection $r) => $r->where('status', $filters['status']));

        return response()->json([
            'data' => $filtered->values(),
            'counts' => $counts,
        ]);
    }

    /**
     * Director correction of a family's staged data — either the shared household section or one
     * child's section. Locked out entirely once the family's submission is aprobado.
     */
    public function update(Request $request, int $campaignId, int $familyId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);
        $draft = $this->draftForFamily($campaign, $familyId);

        abort_if($draft->approved_at !== null, 422, 'Esta solicitud ya fue aprobada y no admite ediciones.');

        $data = $request->validate([
            'scope' => ['required', Rule::in(['household', 'student'])],
            'studentId' => ['required_if:scope,student', 'integer'],
            'changes' => ['required', 'array'],
        ]);

        if ($data['scope'] === 'household') {
            $old = $draft->draft_payload ?? [];
            $new = DraftPayloadMerger::merge($old, $data['changes']);

            $this->fieldChangeRecorder->recordFamilyDraftChanges($draft, $old, $new, ActorType::Admin, $admin->id);

            $draft->update(['draft_payload' => $new]);
        } else {
            $form = PreEnrollmentForm::where('campaign_id', $campaign->id)
                ->where('family_id', $familyId)
                ->where('student_id', $data['studentId'])
                ->firstOrFail();

            $field = $form->submitted_snapshot !== null ? 'submitted_snapshot' : 'draft_payload';
            $old = $form->{$field} ?? [];
            $new = DraftPayloadMerger::merge($old, $data['changes']);

            $this->fieldChangeRecorder->recordFormChanges($form, $old, $new, ActorType::Admin, $admin->id);

            $form->update([$field => $new]);
        }

        return response()->json($this->formatSubmission($campaign, $draft->fresh()));
    }

    /**
     * Sends every one of the family's submitted forms back to in_progress. Hard-blocked once
     * aprobado — this is the one status transition the spec forbids outright, not a soft warning.
     */
    public function reopen(Request $request, int $campaignId, int $familyId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);
        $draft = $this->draftForFamily($campaign, $familyId);

        abort_if($draft->approved_at !== null, 422, 'Esta solicitud ya fue aprobada y no puede reabrirse.');
        abort_if($draft->submitted_at === null, 422, 'Solo una solicitud enviada puede reabrirse.');

        $data = $request->validate(['note' => ['nullable', 'string']]);

        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $familyId)
            ->where('is_excluded', false)
            ->where('status', PreEnrollmentFormStatus::Submitted)
            ->get();

        foreach ($forms as $form) {
            $form->update([
                'status' => PreEnrollmentFormStatus::InProgress,
                'family_notes' => $data['note'] ?? $form->family_notes,
            ]);
        }

        $draft->update(['submitted_at' => null, 'reopened_at' => now()]);

        return response()->json($this->formatSubmission($campaign, $draft->fresh()));
    }

    /**
     * Approves every one of the family's submitted children in one transaction, pushing each to
     * live data via the existing FormApprovalService, then stamps the family draft aprobado.
     * Nothing in this feature can edit or reopen a submission past this point.
     */
    public function approve(Request $request, int $campaignId, int $familyId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);
        $draft = $this->draftForFamily($campaign, $familyId);

        abort_if($draft->approved_at !== null, 422, 'Esta solicitud ya fue aprobada.');
        abort_if($draft->submitted_at === null, 422, 'Solo una solicitud enviada puede aprobarse.');

        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $familyId)
            ->where('is_excluded', false)
            ->where('status', PreEnrollmentFormStatus::Submitted)
            ->get();

        abort_if($forms->isEmpty(), 422, 'No hay formularios enviados para aprobar en esta familia.');

        try {
            DB::transaction(function () use ($forms, $admin, $draft) {
                foreach ($forms as $form) {
                    $this->formApprovalService->approve($form, $admin->id);
                }

                $draft->update(['approved_at' => now(), 'approved_by' => $admin->id]);
            });
        } catch (FormNotSubmittedException) {
            throw ValidationException::withMessages([
                'status' => 'Solo una solicitud enviada puede aprobarse.',
            ]);
        }

        return response()->json($this->formatSubmission($campaign, $draft->fresh()));
    }

    private function draftForFamily(PreEnrollmentCampaign $campaign, int $familyId): PreEnrollmentFamilyDraft
    {
        return PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)
            ->where('family_id', $familyId)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSubmission(PreEnrollmentCampaign $campaign, PreEnrollmentFamilyDraft $draft): array
    {
        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $draft->family_id)
            ->where('is_excluded', false)
            ->get();

        return [
            'familyId' => $draft->family_id,
            'status' => $draft->statusGiven($forms),
            'submittedAt' => $draft->submitted_at?->toIso8601String(),
            'approvedAt' => $draft->approved_at?->toIso8601String(),
            'reopenedAt' => $draft->reopened_at?->toIso8601String(),
        ];
    }
}
