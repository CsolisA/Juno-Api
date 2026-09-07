<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Enums\AdminUserType;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $years = AcademicYear::where('kinder_id', $admin->kinder_id)
            ->orderByDesc('year')
            ->get();

        return response()->json($years->map(fn (AcademicYear $year) => $this->format($year)));
    }

    /**
     * Activates the given academic year and closes whichever year is currently activo, in one
     * transaction. Director-only, and only a year still in planeacion may be activated.
     */
    public function activate(Request $request, int $academicYearId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        abort_unless($admin->type === AdminUserType::Director, 403, 'Solo el director puede activar años lectivos.');

        $year = $this->forAdmin($academicYearId, $admin);

        abort_unless($year->status === AcademicYearStatus::Planeacion, 422, 'Solo un año en planeación puede activarse.');

        DB::transaction(function () use ($year) {
            AcademicYear::where('kinder_id', $year->kinder_id)
                ->where('status', AcademicYearStatus::Activo)
                ->update(['status' => AcademicYearStatus::Cerrado]);

            $year->update(['status' => AcademicYearStatus::Activo]);
        });

        return response()->json($this->format($year->fresh()));
    }

    /**
     * Manual override for campaignConfirmed — independent of any Pre-Matrícula campaign's own
     * status, per §3. Director-only, same as activation.
     */
    public function confirm(Request $request, int $academicYearId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        abort_unless($admin->type === AdminUserType::Director, 403, 'Solo el director puede confirmar la campaña.');

        $year = $this->forAdmin($academicYearId, $admin);

        $data = $request->validate([
            'confirmed' => ['required', 'boolean'],
        ]);

        $year->update(['campaign_confirmed' => $data['confirmed']]);

        return response()->json($this->format($year->fresh()));
    }

    /**
     * Fetch an academic year, 404ing if it doesn't belong to the given admin's kinder.
     */
    private function forAdmin(int $id, AdminUser $admin): AcademicYear
    {
        return AcademicYear::where('kinder_id', $admin->kinder_id)->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(AcademicYear $year): array
    {
        return [
            'id' => $year->id,
            'year' => (string) $year->year,
            'status' => $year->status->value,
            'campaignConfirmed' => $year->campaign_confirmed,
        ];
    }
}
