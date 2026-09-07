<?php

namespace Tests\Feature\Admin\PreEnrollment;

use App\Enums\AdminUserType;
use App\Models\AdminUser;
use App\Models\Kinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_cannot_list_pre_enrollment_campaigns(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson('/api/admin/pre-enrollment/campaigns')->assertStatus(403);
    }

    public function test_assistant_cannot_list_pre_enrollment_campaigns(): void
    {
        $kinder = Kinder::factory()->create();
        $assistant = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Assistant]);

        Sanctum::actingAs($assistant, ['*']);

        $this->getJson('/api/admin/pre-enrollment/campaigns')->assertStatus(403);
    }

    public function test_director_can_still_list_pre_enrollment_campaigns(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);

        Sanctum::actingAs($director, ['*']);

        $this->getJson('/api/admin/pre-enrollment/campaigns')->assertOk();
    }
}
