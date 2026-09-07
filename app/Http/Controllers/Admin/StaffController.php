<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Enums\AdminUserType;
use App\Enums\CredentialMethod;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Group;
use App\Services\Staff\StaffCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    /**
     * camelCase request/response key => admin_users column, for the fields directors and staff
     * are allowed to write.
     */
    private const FIELD_MAP = [
        'name' => 'name',
        'email' => 'email',
        'phone' => 'phone',
        'type' => 'type',
        'address' => 'address',
        'emergencyPhone' => 'emergency_phone',
        'emergencyName' => 'emergency_name',
        'hireDate' => 'hire_date',
        'examDate' => 'psych_exam_date',
    ];

    public function __construct(private readonly StaffCredentialService $credentialService) {}

    /**
     * Full staff listing, director-only, filterable by status.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $staff = AdminUser::where('kinder_id', $admin->kinder_id)
            ->when(($data['status'] ?? null) === 'active', fn ($query) => $query->where('status', true))
            ->when(($data['status'] ?? null) === 'inactive', fn ($query) => $query->where('status', false))
            ->orderBy('name')
            ->get();

        return response()->json($staff->map(fn (AdminUser $user) => $this->format($user)));
    }

    /**
     * Lightweight active-staff listing (id/name/type only) open to any authenticated admin —
     * used to populate pickers such as the group detail screen's professor/assistant selects.
     */
    public function roster(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_map(fn (AdminUserType $case) => $case->value, AdminUserType::cases()))],
        ]);

        $staff = AdminUser::where('kinder_id', $admin->kinder_id)
            ->where('status', true)
            ->when(isset($data['type']), fn ($query) => $query->where('type', $data['type']))
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return response()->json($staff->map(fn (AdminUser $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->type->value,
        ]));
    }

    public function show(Request $request, int $staffId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        return response()->json($this->format($this->forAdmin($staffId, $admin)));
    }

    /**
     * Creates a staff member and issues credentials via the chosen method (§2). The plaintext
     * temporary password, when applicable, is returned once here and never stored or re-exposed.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('admin_users', 'email')],
            'phone' => ['required', 'string'],
            'type' => ['required', Rule::in(array_map(fn (AdminUserType $case) => $case->value, AdminUserType::cases()))],
            'address' => ['required', 'string'],
            'emergencyPhone' => ['required', 'string'],
            'emergencyName' => ['required', 'string'],
            'birthDate' => ['required', 'date'],
            'hireDate' => ['required', 'date'],
            'examDate' => ['nullable', 'date'],
            'credentialMethod' => ['required', Rule::in(array_map(fn (CredentialMethod $case) => $case->value, CredentialMethod::cases()))],
        ]);

        $staff = AdminUser::create([
            'kinder_id' => $admin->kinder_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'type' => $data['type'],
            'address' => $data['address'],
            'emergency_phone' => $data['emergencyPhone'],
            'emergency_name' => $data['emergencyName'],
            'birth_date' => $data['birthDate'],
            'hire_date' => $data['hireDate'],
            'psych_exam_date' => $data['examDate'] ?? null,
            'status' => false,
        ]);

        $plainPassword = $this->credentialService->issue($staff, CredentialMethod::from($data['credentialMethod']));

        return response()->json([
            ...$this->format($staff->fresh()),
            'temporaryPassword' => $plainPassword,
        ], 201);
    }

    /**
     * Full field set, director-only.
     */
    public function update(Request $request, int $staffId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $staff = $this->forAdmin($staffId, $admin);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('admin_users', 'email')->ignore($staff->id)],
            'phone' => ['sometimes', 'string'],
            'type' => ['sometimes', Rule::in(array_map(fn (AdminUserType $case) => $case->value, AdminUserType::cases()))],
            'address' => ['sometimes', 'string'],
            'emergencyPhone' => ['sometimes', 'string'],
            'emergencyName' => ['sometimes', 'string'],
            'hireDate' => ['sometimes', 'date'],
            'examDate' => ['sometimes', 'nullable', 'date'],
        ]);

        $staff->update($this->mapToColumns($data));

        return response()->json($this->format($staff->fresh()));
    }

    /**
     * Self-edit, restricted to a small field set (§2) — any field outside that set is rejected
     * outright (named in the error), never silently dropped.
     */
    public function updateSelf(Request $request): JsonResponse
    {
        /** @var AdminUser $staff */
        $staff = $request->user();

        $allowed = ['phone', 'address', 'emergencyPhone', 'emergencyName'];
        $disallowed = array_diff(array_keys($request->all()), $allowed);

        if (! empty($disallowed)) {
            throw ValidationException::withMessages([
                'fields' => 'No tienes permiso para modificar: '.implode(', ', $disallowed),
            ]);
        }

        $data = $request->validate([
            'phone' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
            'emergencyPhone' => ['sometimes', 'string'],
            'emergencyName' => ['sometimes', 'string'],
        ]);

        $staff->update($this->mapToColumns($data));

        return response()->json($this->format($staff->fresh()));
    }

    /**
     * Blocked while the staff member holds a professor/assistant seat on a group whose academic
     * year is still activo or planeacion (§2) — closed/historical years never block.
     */
    public function deactivate(Request $request, int $staffId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $staff = $this->forAdmin($staffId, $admin);

        $blockingGroups = Group::whereHas(
            'academicYear',
            fn ($query) => $query->where('kinder_id', $admin->kinder_id)
                ->whereIn('status', [AcademicYearStatus::Activo, AcademicYearStatus::Planeacion]),
        )
            ->where(fn ($query) => $query->where('professor_id', $staff->id)->orWhere('assistant_id', $staff->id))
            ->with(['grade', 'academicYear'])
            ->get();

        if ($blockingGroups->isNotEmpty()) {
            return response()->json([
                'message' => 'Este miembro del personal tiene grupos asignados y no puede desactivarse.',
                'groups' => $blockingGroups->map(fn (Group $group) => [
                    'id' => $group->id,
                    'level' => $group->grade->name,
                    'academicYear' => [
                        'id' => $group->academicYear->id,
                        'year' => (string) $group->academicYear->year,
                    ],
                ])->values(),
            ], 422);
        }

        $staff->update(['status' => false]);

        return response()->json($this->format($staff->fresh()));
    }

    public function reactivate(Request $request, int $staffId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $staff = $this->forAdmin($staffId, $admin);

        $staff->update(['status' => true]);

        return response()->json($this->format($staff->fresh()));
    }

    /**
     * Reissues credentials using the credentialMethod already on file, or an override from the
     * request body — reuses the same two code paths as creation (§2).
     */
    public function resetPassword(Request $request, int $staffId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $staff = $this->forAdmin($staffId, $admin);

        abort_unless($staff->status, 422, 'Solo se pueden restablecer credenciales de personal activo.');

        $data = $request->validate([
            'credentialMethod' => ['sometimes', Rule::in(array_map(fn (CredentialMethod $case) => $case->value, CredentialMethod::cases()))],
        ]);

        $method = isset($data['credentialMethod'])
            ? CredentialMethod::from($data['credentialMethod'])
            : $staff->credential_method;

        abort_if($method === null, 422, 'No hay un método de credenciales registrado; especifique uno.');

        $plainPassword = $this->credentialService->issue($staff, $method);

        return response()->json([
            ...$this->format($staff->fresh()),
            'temporaryPassword' => $plainPassword,
        ]);
    }

    /**
     * Fetch a staff member, 404ing if they don't belong to the given admin's kinder.
     */
    private function forAdmin(int $id, AdminUser $admin): AdminUser
    {
        return AdminUser::where('kinder_id', $admin->kinder_id)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapToColumns(array $data): array
    {
        $mapped = [];
        foreach ($data as $key => $value) {
            $mapped[self::FIELD_MAP[$key] ?? $key] = $value;
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    private function format(AdminUser $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'type' => $staff->type->value,
            'status' => $staff->status,
            'address' => $staff->address,
            'emergencyPhone' => $staff->emergency_phone,
            'emergencyName' => $staff->emergency_name,
            'birthDate' => $staff->birth_date?->toDateString(),
            'hireDate' => $staff->hire_date?->toDateString(),
            'examDate' => $staff->psych_exam_date?->toDateString(),
            'credentialMethod' => $staff->credential_method?->value,
            'mustResetPassword' => $staff->must_reset_password,
            'createdAt' => $staff->created_at?->toIso8601String(),
        ];
    }
}
