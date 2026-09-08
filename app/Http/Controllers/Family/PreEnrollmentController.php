<?php

namespace App\Http\Controllers\Family;

use App\Enums\ActorType;
use App\Enums\PreEnrollmentCampaignStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Family\PreEnrollment\ScopesPreEnrollmentToFamily;
use App\Models\Family;
use App\Models\PreEnrollmentForm;
use App\Services\PreEnrollment\CampaignAutoCloseService;
use App\Services\PreEnrollment\DraftPayloadMerger;
use App\Services\PreEnrollment\FieldChangeRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PreEnrollmentController extends Controller
{
    use ScopesPreEnrollmentToFamily;

    public function __construct(
        private readonly FieldChangeRecorder $fieldChangeRecorder,
        private readonly CampaignAutoCloseService $campaignAutoCloseService,
    ) {}

    /**
     * The alert payload for the portal dashboard — null when this family has nothing to act on:
     * no campaign has been opened to them yet, or every one of their students was excluded (they
     * never see a campaign or reason in that case, per the plan's rule 7 in §10).
     */
    public function active(Request $request): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $forms = PreEnrollmentForm::where('family_id', $family->id)
            ->where('is_excluded', false)
            ->whereHas('campaign', fn ($query) => $query->whereIn('status', [
                PreEnrollmentCampaignStatus::Open,
                PreEnrollmentCampaignStatus::Closed,
            ]))
            ->with('campaign')
            ->get();

        if ($forms->isEmpty()) {
            return response()->json(null);
        }

        $campaign = $forms
            ->pluck('campaign')
            ->unique('id')
            ->sortByDesc(fn ($campaign) => $campaign->opened_at ?? $campaign->created_at)
            ->first();

        $formsForCampaign = $forms->where('campaign_id', $campaign->id);

        $doneStatuses = [PreEnrollmentFormStatus::Submitted, PreEnrollmentFormStatus::Approved, PreEnrollmentFormStatus::Applied];
        $studentsPending = $formsForCampaign->reject(fn (PreEnrollmentForm $form) => in_array($form->status, $doneStatuses, true))->count();
        $completionPercent = (int) round($formsForCampaign->avg('completion_percent'));

        $status = match (true) {
            $campaign->status === PreEnrollmentCampaignStatus::Closed && $studentsPending > 0 => 'closed_without_submitting',
            $studentsPending === 0 => 'submitted',
            $completionPercent === 0 => 'not_started',
            default => 'in_progress',
        };

        return response()->json([
            'campaignId' => $campaign->id,
            'name' => $campaign->name,
            'dueDate' => $campaign->due_date?->toDateString(),
            'status' => $status,
            'completionPercent' => $completionPercent,
            'studentsPending' => $studentsPending,
        ]);
    }

    /**
     * Full bundle: the family's shared household draft plus one section per included student.
     */
    public function show(Request $request, int $campaignId): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $campaign = $this->campaignForFamily($campaignId, $family);
        $draft = $this->familyDraftFor($campaign, $family);

        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $family->id)
            ->where('is_excluded', false)
            ->with(['student', 'targetGrade'])
            ->get();

        return response()->json([
            'campaignId' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'dueDate' => $campaign->due_date?->toDateString(),
            'household' => [
                'revision' => $draft->revision,
                'draftPayload' => $draft->draft_payload,
            ],
            'students' => $forms->map(fn (PreEnrollmentForm $form) => $this->formatForm($form))->values(),
        ]);
    }

    /**
     * Autosave the shared household section. Per §9 Q11, no audit rows are written here —
     * only at submit and on director corrections.
     */
    public function updateFamilyDraft(Request $request, int $campaignId): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $campaign = $this->campaignForFamily($campaignId, $family);
        $this->assertCampaignOpenForEditing($campaign);

        $data = $request->validate([
            'revision' => ['required', 'integer'],
            'changes' => ['required', 'array'],
        ]);

        $draft = $this->familyDraftFor($campaign, $family);

        if ($data['revision'] !== $draft->revision) {
            return response()->json([
                'message' => 'Esta sección fue modificada en otra sesión.',
                'revision' => $draft->revision,
                'draftPayload' => $draft->draft_payload,
            ], 409);
        }

        $draft->update([
            'draft_payload' => DraftPayloadMerger::merge($draft->draft_payload, $data['changes']),
            'revision' => $draft->revision + 1,
        ]);

        return response()->json([
            'revision' => $draft->revision,
            'savedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Autosave one student's section.
     */
    public function updateStudentDraft(Request $request, int $campaignId, int $studentId): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $campaign = $this->campaignForFamily($campaignId, $family);
        $this->assertCampaignOpenForEditing($campaign);

        $data = $request->validate([
            'revision' => ['required', 'integer'],
            'changes' => ['required', 'array'],
            'reportIssue' => ['nullable', 'string'],
        ]);

        $form = $this->formForFamilyAndStudent($campaign, $family, $studentId);

        if ($data['revision'] !== $form->revision) {
            return response()->json([
                'message' => 'Esta sección fue modificada en otra sesión.',
                'revision' => $form->revision,
                'draftPayload' => $form->draft_payload,
            ], 409);
        }

        $newPayload = DraftPayloadMerger::merge($form->draft_payload, $data['changes']);

        $form->draft_payload = $newPayload;
        $form->revision = $form->revision + 1;
        $form->completion_percent = $form->calculateCompletionPercent();
        $form->started_at ??= now();

        if ($form->status === PreEnrollmentFormStatus::Pending) {
            $form->status = PreEnrollmentFormStatus::InProgress;
        }

        if (isset($data['reportIssue'])) {
            $form->family_notes = $data['reportIssue'];
            $form->has_reported_issue = true;
        }

        $form->save();

        return response()->json([
            'revision' => $form->revision,
            'completionPercent' => $form->completion_percent,
            'savedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * Per §9 Q1: one submit covers every included student for this family — not one per child.
     */
    public function submit(Request $request, int $campaignId): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $campaign = $this->campaignForFamily($campaignId, $family);
        $this->assertCampaignOpenForEditing($campaign);

        $draft = $this->familyDraftFor($campaign, $family);

        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $family->id)
            ->where('is_excluded', false)
            ->get();

        $incomplete = $forms->filter(fn (PreEnrollmentForm $form) => $form->calculateCompletionPercent() < 100);

        if ($incomplete->isNotEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'Complete todas las secciones antes de enviar: '.$incomplete->pluck('student_id')->implode(', '),
            ]);
        }

        if (! $draft->hasAnyCompleteGuardian()) {
            throw ValidationException::withMessages([
                'household' => 'Complete al menos los datos de un encargado (madre o padre) antes de enviar.',
            ]);
        }

        foreach ($forms as $form) {
            $snapshot = array_merge($draft->draft_payload, $form->draft_payload);

            $this->fieldChangeRecorder->recordFormSubmission($form, $snapshot, ActorType::Family, $family->id);

            $form->update([
                'submitted_snapshot' => $snapshot,
                'status' => PreEnrollmentFormStatus::Submitted,
                'submitted_at' => now(),
            ]);
        }

        if ($draft->submitted_at === null) {
            $this->fieldChangeRecorder->recordFamilyDraftSubmission($draft, $draft->draft_payload, ActorType::Family, $family->id);
            $draft->update(['submitted_at' => now()]);
        }

        $this->campaignAutoCloseService->evaluate($campaign);

        return response()->json(['submittedAt' => now()->toIso8601String()]);
    }

    public function receipt(Request $request, int $campaignId): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $campaign = $this->campaignForFamily($campaignId, $family);

        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $family->id)
            ->where('is_excluded', false)
            ->with('student')
            ->get();

        return response()->json([
            'campaignId' => $campaign->id,
            'name' => $campaign->name,
            'students' => $forms->map(fn (PreEnrollmentForm $form) => [
                'studentId' => $form->student_id,
                'name' => trim("{$form->student->name} {$form->student->last_name}"),
                'status' => $form->status->value,
                'submittedAt' => $form->submitted_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatForm(PreEnrollmentForm $form): array
    {
        return [
            'formId' => $form->id,
            'studentId' => $form->student_id,
            'name' => trim("{$form->student->name} {$form->student->last_name}"),
            'targetGrade' => $form->targetGrade ? ['id' => $form->targetGrade->id, 'name' => $form->targetGrade->name] : null,
            'status' => $form->status->value,
            'revision' => $form->revision,
            'completionPercent' => $form->completion_percent,
            'draftPayload' => $form->draft_payload,
            'hasReportedIssue' => $form->has_reported_issue,
        ];
    }
}
