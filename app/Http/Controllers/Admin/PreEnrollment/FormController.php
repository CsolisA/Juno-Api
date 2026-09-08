<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Grade;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use App\Services\PreEnrollment\DraftPrefillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    use ScopesCampaignToKinder;

    public function __construct(private readonly DraftPrefillService $draftPrefillService) {}

    /**
     * Paginated, filterable list of forms in a campaign.
     */
    public function index(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);

        $filters = $request->validate([
            'status' => ['nullable', 'string'],
            'gradeId' => ['nullable', 'integer'],
            'targetGradeId' => ['nullable', 'integer'],
            'hasChanges' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string'],
        ]);

        $query = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('is_excluded', false)
            ->with(['student', 'currentGrade', 'targetGrade']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['gradeId'])) {
            $query->where('current_grade_id', $filters['gradeId']);
        }
        if (isset($filters['targetGradeId'])) {
            $query->where('target_grade_id', $filters['targetGradeId']);
        }
        if (isset($filters['hasChanges'])) {
            $query->where('has_reported_issue', $filters['hasChanges']);
        }
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        $forms = $query->paginate(25);

        return response()->json([
            'data' => collect($forms->items())->map(fn (PreEnrollmentForm $form) => $this->formatSummary($form))->values(),
            'currentPage' => $forms->currentPage(),
            'lastPage' => $forms->lastPage(),
            'total' => $forms->total(),
        ]);
    }

    /**
     * Merged review view — every known field as {current, proposed, changed}. "current" is read
     * live (not the campaign-open sourceValue snapshot) so a director reviewing weeks later still
     * compares against today's real data.
     */
    public function show(Request $request, int $campaignId, int $formId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);
        $form = $this->formForCampaign($campaign, $formId);

        $currentFamily = $this->draftPrefillService->prefillFamilyDraft($form->family);
        $currentStudent = $this->draftPrefillService->prefillFormDraft($form);
        $current = array_merge($currentFamily, $currentStudent);

        $submitted = $form->submitted_snapshot ?? $form->draft_payload ?? [];

        $fields = [];

        foreach ($current as $path => $entry) {
            $currentValue = $entry['value'];
            $proposedValue = array_key_exists($path, $submitted) ? ($submitted[$path]['value'] ?? null) : $currentValue;

            $fields[$path] = [
                'current' => $currentValue,
                'proposed' => $proposedValue,
                'changed' => $currentValue !== $proposedValue,
            ];
        }

        foreach ($submitted as $path => $entry) {
            if (! isset($fields[$path])) {
                $fields[$path] = [
                    'current' => null,
                    'proposed' => is_array($entry) ? ($entry['value'] ?? null) : $entry,
                    'changed' => true,
                ];
            }
        }

        return response()->json([
            'formId' => $form->id,
            'status' => $form->status->value,
            'familyNotes' => $form->family_notes,
            'hasReportedIssue' => $form->has_reported_issue,
            'currentGrade' => ['id' => $form->currentGrade->id, 'name' => $form->currentGrade->name],
            'targetGrade' => $form->targetGrade ? ['id' => $form->targetGrade->id, 'name' => $form->targetGrade->name] : null,
            'fields' => $fields,
        ]);
    }

    /**
     * Level override from the form-detail screen (separate from the campaign-creation-time
     * override, per §9 Q3 — both write the same tracking columns).
     */
    public function targetGrade(Request $request, int $campaignId, int $formId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);
        $form = $this->formForCampaign($campaign, $formId);

        $data = $request->validate([
            'targetGradeId' => ['required', 'integer', 'exists:grades,id'],
        ]);

        $form->update([
            'target_grade_id' => $data['targetGradeId'],
            'target_grade_overridden' => true,
            'target_grade_overridden_by' => $admin->id,
            'target_grade_overridden_at' => now(),
        ]);

        return response()->json([
            'formId' => $form->id,
            'targetGrade' => ['id' => $data['targetGradeId'], 'name' => Grade::findOrFail($data['targetGradeId'])->name],
        ]);
    }

    public function changes(Request $request, int $campaignId, int $formId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $campaign = $this->campaignForAdmin($campaignId, $admin);
        $form = $this->formForCampaign($campaign, $formId);

        $changes = $form->fieldChanges()->orderBy('created_at')->get();

        return response()->json($changes->map(fn ($change) => [
            'fieldPath' => $change->field_path,
            'oldValue' => $change->old_value,
            'newValue' => $change->new_value,
            'actorType' => $change->actor_type->value,
            'actorId' => $change->actor_id,
            'createdAt' => $change->created_at->toIso8601String(),
        ]));
    }

    private function formForCampaign(PreEnrollmentCampaign $campaign, int $formId): PreEnrollmentForm
    {
        return PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->with(['student', 'family.guardians', 'currentGrade', 'targetGrade'])
            ->findOrFail($formId);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSummary(PreEnrollmentForm $form): array
    {
        return [
            'formId' => $form->id,
            'studentId' => $form->student_id,
            'name' => trim("{$form->student->name} {$form->student->last_name} {$form->student->last_name_two}"),
            'currentGrade' => ['id' => $form->currentGrade->id, 'name' => $form->currentGrade->name],
            'targetGrade' => $form->targetGrade ? ['id' => $form->targetGrade->id, 'name' => $form->targetGrade->name] : null,
            'status' => $form->status->value,
            'completionPercent' => $form->completion_percent,
            'hasReportedIssue' => $form->has_reported_issue,
            'submittedAt' => $form->submitted_at?->toIso8601String(),
        ];
    }
}
