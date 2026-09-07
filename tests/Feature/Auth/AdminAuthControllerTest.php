<?php

namespace Tests\Feature\Auth;

use App\Models\AdminUser;
use App\Models\Kinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rejected_for_a_staff_member_with_no_password_set_yet(): void
    {
        $kinder = Kinder::factory()->create();
        AdminUser::factory()->create([
            'kinder_id' => $kinder->id,
            'email' => 'invited@example.test',
            'password' => null,
            'status' => false,
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'invited@example.test',
            'password' => 'anything',
        ]);

        $response->assertStatus(422);
    }
}
