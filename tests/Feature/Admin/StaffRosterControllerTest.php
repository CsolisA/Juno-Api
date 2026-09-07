<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminUserType;
use App\Models\AdminUser;
use App\Models\Kinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffRosterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_staff_for_the_admins_kinder_only(): void
    {
        $kinder = Kinder::factory()->create();
        $otherKinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);

        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor, 'status' => true]);
        AdminUser::factory()->create(['kinder_id' => $kinder->id, 'status' => false]);
        AdminUser::factory()->create(['kinder_id' => $otherKinder->id]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/staff/roster');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($professor->id));
        $this->assertTrue($ids->contains($admin->id));
        $this->assertCount(2, $ids);
    }

    public function test_filters_by_type(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Assistant]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/admin/staff/roster?type=professor');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $professor->id);
    }

    public function test_roster_is_open_to_non_director_admins(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson('/api/admin/staff/roster')->assertOk();
    }
}
