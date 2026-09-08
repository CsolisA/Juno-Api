<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentCampaignStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use App\Notifications\PreEnrollmentLateAddNotification;
use App\Services\PreEnrollment\CampaignCreationService;
use App\Services\PreEnrollment\CampaignReadinessService;
use App\Services\PreEnrollment\DraftPrefillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class LateAddController extends Controller
{
    use ScopesCampaignToKinder;

    public function __construct(
        private readonly CampaignCreationService $campaignCreationService,
        private readonly CampaignReadinessService $campaignReadinessService,
        private readonly DraftPrefillService $draftPrefillService,
    ) {}

    /**
     * Includes a student who either wasn't in the campaign's original eligible pool at all, or
     * was in it but excluded. Works in any campaign state except borrador (use the selection
     * screen there instead) and never affects auto-close or re-opens an already-closed campaign.
     */
    public function store(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        abort_if(
            $campaign->status === PreEnrollmentCampaignStatus::Draft,
            422,
            'Usa la pantalla de selección mientras la solicitud está en borrador.',
        );

        $data = $request->validate([
            'studentId' => ['required', 'integer'],
        ]);

        /** @var Student $student */
        $student = Student::whereHas('family', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->with('family')
            ->findOrFail($data['studentId']);

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('student_id', $student->id)
            ->first();

        abort_if($form && ! $form->is_excluded, 422, 'Este estudiante ya está incluido en la solicitud.');

        $form = $form
            ? $this->reincludeForm($form)
            : $this->createLateForm($campaign, $student);

        if (empty($form->draft_payload)) {
            $form->update(['draft_payload' => $this->draftPrefillService->prefillFormDraft($form)]);
        }

        $draft = PreEnrollmentFamilyDraft::firstOrCreate(
            ['campaign_id' => $campaign->id, 'family_id' => $student->family_id],
            ['draft_payload' => []],
        );

        if (empty($draft->draft_payload)) {
            $draft->update(['draft_payload' => $this->draftPrefillService->prefillFamilyDraft($student->family)]);
        }

        $this->sendLateAddNotification($campaign, $student);

        $form->update(['notified_at' => now()]);

        return response()->json([
            'formId' => $form->id,
            'studentId' => $student->id,
            'status' => $form->fresh()->status->value,
        ], 201);
    }

    private function reincludeForm(PreEnrollmentForm $form): PreEnrollmentForm
    {
        $form->update([
            'is_excluded' => false,
            'exclusion_reason' => null,
            'exclusion_reason_detail' => null,
            'excluded_by' => null,
            'excluded_at' => null,
            'status' => PreEnrollmentFormStatus::Pending,
            'added_late' => true,
        ]);

        return $form->fresh();
    }

    private function createLateForm(PreEnrollmentCampaign $campaign, Student $student): PreEnrollmentForm
    {
        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->latest('id')
            ->first();

        abort_unless($enrollment, 422, 'No se encontró una matrícula activa para proyectar el nivel de este estudiante.');

        $progression = $this->campaignCreationService->resolveProgression($enrollment);

        abort_if($progression->isGraduating, 422, 'Este estudiante egresa y no puede incluirse en la solicitud.');

        return PreEnrollmentForm::create([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'family_id' => $student->family_id,
            'current_grade_id' => $enrollment->grade_id,
            'current_group_id' => $enrollment->group_id,
            'target_grade_id' => $progression->targetGrade?->id,
            'status' => PreEnrollmentFormStatus::Pending,
            'is_excluded' => false,
            'added_late' => true,
            'draft_payload' => [],
        ]);
    }

    private function sendLateAddNotification(PreEnrollmentCampaign $campaign, Student $student): void
    {
        foreach ($this->campaignReadinessService->notifiableEmails($student->family) as $email) {
            Notification::route('mail', $email)
                ->notify(new PreEnrollmentLateAddNotification($campaign, $student->family, $student));
        }
    }
}
