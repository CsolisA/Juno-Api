<?php

namespace Tests\Feature\Admin;

use App\Enums\AcademicYearStatus;
use App\Enums\AdminUserType;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Kinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicYearControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_planeacion_year_closes_the_currently_active_one(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);

        $activeYear = AcademicYear::factory()->activo()->create(['kinder_id' => $kinder->id, 'year' => 2026]);
        $nextYear = AcademicYear::factory()->planeacion()->create(['kinder_id' => $kinder->id, 'year' => 2027]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson("/api/admin/academic-years/{$nextYear->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('status', AcademicYearStatus::Activo->value);

        $this->assertSame(AcademicYearStatus::Activo, $nextYear->fresh()->status);
        $this->assertSame(AcademicYearStatus::Cerrado, $activeYear->fresh()->status);
    }

    public function test_activating_a_year_not_in_planeacion_fails(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);

        $closedYear = AcademicYear::factory()->cerrado()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->postJson("/api/admin/academic-years/{$closedYear->id}/activate");

        $response->assertStatus(422);
        $this->assertSame(AcademicYearStatus::Cerrado, $closedYear->fresh()->status);
    }

    public function test_activation_is_restricted_to_director(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $year = AcademicYear::factory()->planeacion()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->postJson("/api/admin/academic-years/{$year->id}/activate")->assertStatus(403);

        $this->assertSame(AcademicYearStatus::Planeacion, $year->fresh()->status);
    }

    public function test_confirm_toggles_campaign_confirmed(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $year = AcademicYear::factory()->create(['kinder_id' => $kinder->id, 'campaign_confirmed' => true]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->patchJson("/api/admin/academic-years/{$year->id}/confirm", ['confirmed' => false]);

        $response->assertOk();
        $response->assertJsonPath('campaignConfirmed', false);
        $this->assertFalse($year->fresh()->campaign_confirmed);
    }

    public function test_index_lists_years_for_the_admins_kinder_only(): void
    {
        $kinder = Kinder::factory()->create();
        $otherKinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);

        AcademicYear::factory()->create(['kinder_id' => $kinder->id, 'year' => 2026]);
        AcademicYear::factory()->create(['kinder_id' => $otherKinder->id, 'year' => 2026]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/academic-years');

        $response->assertOk();
        $response->assertJsonCount(1);
    }
}
