<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminUserType;
use App\Enums\CredentialMethod;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use App\Notifications\StaffInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    private function newStaffPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'ana.perez@example.test',
            'phone' => '8888-1111',
            'type' => AdminUserType::Professor->value,
            'address' => 'San José',
            'emergencyPhone' => '8888-2222',
            'emergencyName' => 'Juan Pérez',
            'birthDate' => '1990-01-01',
            'hireDate' => '2024-01-01',
        ], $overrides);
    }

    public function test_create_staff_with_temp_password_returns_plaintext_once_and_is_active(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson('/api/admin/staff', $this->newStaffPayload([
            'credentialMethod' => CredentialMethod::TempPassword->value,
        ]));

        $response->assertCreated();
        $plainPassword = $response->json('temporaryPassword');
        $this->assertNotEmpty($plainPassword);
        $response->assertJsonPath('status', true);
        $response->assertJsonPath('mustResetPassword', true);

        $staff = AdminUser::where('email', 'ana.perez@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($plainPassword, $staff->password));
        $this->assertTrue($staff->status);
        $this->assertTrue($staff->must_reset_password);
    }

    public function test_create_staff_with_invite_sends_notification_and_stays_inactive(): void
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson('/api/admin/staff', $this->newStaffPayload([
            'credentialMethod' => CredentialMethod::Invite->value,
        ]));

        $response->assertCreated();
        $this->assertNull($response->json('temporaryPassword'));
        $response->assertJsonPath('status', false);

        $staff = AdminUser::where('email', 'ana.perez@example.test')->firstOrFail();
        $this->assertNull($staff->password);
        $this->assertNotNull($staff->invite_token);
        $this->assertFalse($staff->status);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            StaffInviteNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana.perez@example.test',
        );
    }

    public function test_accept_invite_with_valid_token_activates_account(): void
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        Sanctum::actingAs($director, ['*']);

        $this->postJson('/api/admin/staff', $this->newStaffPayload([
            'credentialMethod' => CredentialMethod::Invite->value,
        ]))->assertCreated();

        $staff = AdminUser::where('email', 'ana.perez@example.test')->firstOrFail();
        $token = $staff->invite_token;

        $response = $this->postJson('/api/auth/staff/accept-invite', ['token' => $token, 'password' => 'newpassword123']);

        $response->assertOk();

        $staff->refresh();
        $this->assertTrue($staff->status);
        $this->assertNull($staff->invite_token);
        $this->assertNull($staff->invite_expires_at);
        $this->assertTrue(Hash::check('newpassword123', $staff->password));
    }

    public function test_accept_invite_with_expired_token_is_rejected(): void
    {
        $kinder = Kinder::factory()->create();
        $staff = AdminUser::factory()->create([
            'kinder_id' => $kinder->id,
            'password' => null,
            'status' => false,
            'invite_token' => 'expired-token',
            'invite_expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/auth/staff/accept-invite', [
            'token' => 'expired-token',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($staff->fresh()->status);
        $this->assertNotNull($staff->fresh()->invite_token);
    }

    public function test_deactivate_is_blocked_when_staff_holds_an_active_group_assignment(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->activo()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $academicYear->id,
            'professor_id' => $professor->id,
        ]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson("/api/admin/staff/{$professor->id}/deactivate");

        $response->assertStatus(422);
        $response->assertJsonPath('groups.0.id', $group->id);
        $this->assertTrue($professor->fresh()->status);
    }

    public function test_deactivate_succeeds_once_unassigned(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $closedYear = AcademicYear::factory()->cerrado()->create(['kinder_id' => $kinder->id]);
        Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $closedYear->id,
            'professor_id' => $professor->id,
        ]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson("/api/admin/staff/{$professor->id}/deactivate");

        $response->assertOk();
        $this->assertFalse($professor->fresh()->status);
    }

    public function test_reactivate_sets_status_active(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $inactiveStaff = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'status' => false]);

        Sanctum::actingAs($director, ['*']);

        $this->postJson("/api/admin/staff/{$inactiveStaff->id}/reactivate")->assertOk();

        $this->assertTrue($inactiveStaff->fresh()->status);
    }

    public function test_update_self_rejects_disallowed_fields(): void
    {
        $kinder = Kinder::factory()->create();
        $staff = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->patchJson('/api/admin/staff/me', [
            'phone' => '8888-9999',
            'type' => AdminUserType::Director->value,
        ]);

        $response->assertStatus(422);
        $this->assertSame(AdminUserType::Professor, $staff->fresh()->type);
    }

    public function test_update_self_allows_permitted_fields(): void
    {
        $kinder = Kinder::factory()->create();
        $staff = AdminUser::factory()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($staff, ['*']);

        $response = $this->patchJson('/api/admin/staff/me', ['phone' => '8888-9999']);

        $response->assertOk();
        $this->assertSame('8888-9999', $staff->fresh()->phone);
    }

    public function test_full_update_is_director_only(): void
    {
        $kinder = Kinder::factory()->create();
        $staff = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $otherStaff = AdminUser::factory()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($staff, ['*']);

        $this->patchJson("/api/admin/staff/{$otherStaff->id}", ['phone' => '8888-0000'])->assertStatus(403);
    }

    public function test_reset_password_reuses_credential_method_on_file(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $staff = AdminUser::factory()->create([
            'kinder_id' => $kinder->id,
            'credential_method' => CredentialMethod::TempPassword,
            'status' => true,
        ]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson("/api/admin/staff/{$staff->id}/reset-password");

        $response->assertOk();
        $plainPassword = $response->json('temporaryPassword');
        $this->assertNotEmpty($plainPassword);
        $this->assertTrue(Hash::check($plainPassword, $staff->fresh()->password));
        $this->assertTrue($staff->fresh()->must_reset_password);
    }

    public function test_index_is_director_only(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson('/api/admin/staff')->assertStatus(403);
    }
}
