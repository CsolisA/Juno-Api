<?php

namespace Tests\Feature\Catalog;

use App\Models\AdminUser;
use App\Models\Grade;
use App\Models\Schedule;
use Database\Seeders\ProvinceCantonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreEnrollmentCatalogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(AdminUser::factory()->create(), ['*']);
    }

    public function test_grades_catalog_is_ordered(): void
    {
        Grade::factory()->create(['name' => 'Kinder', 'order' => 2, 'is_final' => false]);
        Grade::factory()->create(['name' => 'Maternal', 'order' => 0, 'is_final' => false]);

        $response = $this->getJson('/api/catalogs/grades');

        $response->assertOk();
        $this->assertSame(['Maternal', 'Kinder'], collect($response->json())->pluck('name')->all());
    }

    public function test_schedules_catalog_with_no_pivot_rows_returns_all_active_schedules(): void
    {
        $grade = Grade::factory()->create();

        $response = $this->getJson("/api/catalogs/schedules?gradeId={$grade->id}");

        $response->assertOk();
        // The `schedules` migration itself seeds 3 canonical rows (PRESENCIAL/ALTERNO/GUARDERIA);
        // with no schedule_grade pivot rows for this grade, all of them should be offered.
        $this->assertCount(3, $response->json());
    }

    public function test_schedules_catalog_filters_by_pivot_when_present(): void
    {
        $grade = Grade::factory()->create();
        $allowed = Schedule::factory()->create(['code' => 'A', 'is_active' => true, 'display_order' => 1]);
        $notAllowed = Schedule::factory()->create(['code' => 'B', 'is_active' => true, 'display_order' => 2]);
        $allowed->grades()->attach($grade->id, ['is_active' => true]);
        $notAllowed->grades()->attach($grade->id, ['is_active' => false]);

        $response = $this->getJson("/api/catalogs/schedules?gradeId={$grade->id}");

        $response->assertOk();
        $this->assertSame(['A'], collect($response->json())->pluck('code')->all());
    }

    public function test_provinces_and_cantons_catalog(): void
    {
        (new ProvinceCantonSeeder)->run();

        $provinces = $this->getJson('/api/catalogs/provinces')->assertOk()->json();
        $sanJose = collect($provinces)->firstWhere('name', 'San José');
        $this->assertNotNull($sanJose);

        $cantons = $this->getJson("/api/catalogs/cantons?provinceId={$sanJose['id']}")->assertOk()->json();
        $this->assertContains('Escazú', collect($cantons)->pluck('name')->all());
    }
}
