<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Enums\ExclusionReason;
use App\Enums\PreEnrollmentFormStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\PreEnrollmentForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExclusionController extends Controller
{
    use ScopesCampaignToKinder;

    public function update(Request $request, int $campaignId, int $formId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->with(['student', 'currentGrade', 'targetGrade'])
            ->findOrFail($formId);

        abort_if($form->currentGrade->is_final, 422, 'Un estudiante que egresa no puede incluirse ni excluirse manualmente.');

        $data = $this->validated($request);

        $this->applyExclusion($form, $admin, $data['isExcluded'], $data['reason'] ?? null, $data['reasonDetail'] ?? null);

        return response()->json($this->formatStudent($form->fresh(['student', 'currentGrade', 'targetGrade'])));
    }

    public function bulk(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        $bulkData = $request->validate([
            'formIds' => ['required', 'array', 'min:1'],
            'formIds.*' => ['integer'],
        ]);
        $data = $this->validated($request);

        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->whereIn('id', $bulkData['formIds'])
            ->with('currentGrade')
            ->get()
            ->reject(fn (PreEnrollmentForm $form) => $form->currentGrade->is_final);

        foreach ($forms as $form) {
            $this->applyExclusion($form, $admin, $data['isExcluded'], $data['reason'] ?? null, $data['reasonDetail'] ?? null);
        }

        return response()->json(['updated' => $forms->count()]);
    }

    /**
     * @return array{isExcluded: bool, reason?: string|null, reasonDetail?: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'isExcluded' => ['required', 'boolean'],
            'reason' => [
                'nullable',
                'required_if:isExcluded,true',
                Rule::in(array_map(fn (ExclusionReason $case) => $case->value, array_filter(
                    ExclusionReason::cases(),
                    fn (ExclusionReason $case) => $case !== ExclusionReason::Graduating,
                ))),
            ],
            'reasonDetail' => ['nullable', 'string', 'required_if:reason,other'],
        ]);
    }

    private function applyExclusion(PreEnrollmentForm $form, AdminUser $admin, bool $isExcluded, ?string $reason, ?string $reasonDetail): void
    {
        if ($isExcluded) {
            $form->update([
                'is_excluded' => true,
                'exclusion_reason' => ExclusionReason::from($reason),
                'exclusion_reason_detail' => $reason === ExclusionReason::Other->value ? $reasonDetail : null,
                'excluded_by' => $admin->id,
                'excluded_at' => now(),
                'status' => PreEnrollmentFormStatus::Excluded,
            ]);

            return;
        }

        $form->update([
            'is_excluded' => false,
            'exclusion_reason' => null,
            'exclusion_reason_detail' => null,
            'excluded_by' => null,
            'excluded_at' => null,
            'status' => PreEnrollmentFormStatus::Pending,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStudent(PreEnrollmentForm $form): array
    {
        $student = $form->student;

        return [
            'formId' => $form->id,
            'studentId' => $student->id,
            'name' => trim("{$student->name} {$student->last_name} {$student->last_name_two}"),
            'currentGrade' => ['id' => $form->currentGrade->id, 'name' => $form->currentGrade->name],
            'targetGrade' => $form->targetGrade ? ['id' => $form->targetGrade->id, 'name' => $form->targetGrade->name] : null,
            'isGraduating' => $form->currentGrade->is_final,
            'isExcluded' => $form->is_excluded,
            'exclusionReason' => $form->exclusion_reason?->value === 'other'
                ? $form->exclusion_reason_detail
                : $form->exclusion_reason?->label(),
        ];
    }
}
