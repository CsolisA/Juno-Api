<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminUserType;
use App\Models\AdminUser;
use App\Models\FamilyInvite;
use App\Models\Kinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FamilyInviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Kinder $kinder;

    private AdminUser $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kinder = Kinder::factory()->create();
        $this->director = AdminUser::factory()->create(['kinder_id' => $this->kinder->id]);
        Sanctum::actingAs($this->director, ['*']);
    }

    public function test_store_returns_link_once_and_stores_only_the_hash(): void
    {
        $response = $this->postJson('/api/admin/family-invites', [
            'label' => 'Familia Pérez',
            'phone' => '8888-8888',
        ])->assertCreated();

        $url = $response->json('url');
        $this->assertStringContainsString('/registro/', $url);

        $token = substr($url, strrpos($url, '/') + 1);
        $invite = FamilyInvite::firstOrFail();
        $this->assertSame(FamilyInvite::hashToken($token), $invite->token_hash);
        $this->assertNotSame($token, $invite->token_hash);
        $this->assertSame('50688888888', $invite->phone);
        $this->assertStringStartsWith('https://wa.me/50688888888?text=', $response->json('whatsappUrl'));
        $this->assertStringContainsString(rawurlencode($url), $response->json('whatsappUrl'));

        $this->getJson('/api/onboarding/session', ['X-Invite-Token' => $token])->assertOk();

        $this->getJson('/api/admin/family-invites')
            ->assertOk()
            ->assertJsonMissingPath('0.url')
            ->assertJsonPath('0.status', 'pending');
    }

    public function test_whatsapp_url_without_phone_opens_the_chooser(): void
    {
        $response = $this->postJson('/api/admin/family-invites', ['label' => 'Sin teléfono'])->assertCreated();

        $this->assertStringStartsWith('https://wa.me/?text=', $response->json('whatsappUrl'));
    }

    public function test_regenerate_invalidates_the_old_link(): void
    {
        $oldToken = 'old-token';
        $invite = FamilyInvite::factory()->withToken($oldToken)->expired()->create(['kinder_id' => $this->kinder->id]);

        $url = $this->postJson("/api/admin/family-invites/{$invite->id}/regenerate")->assertOk()->json('url');
        $newToken = substr($url, strrpos($url, '/') + 1);

        $this->getJson('/api/onboarding/session', ['X-Invite-Token' => $oldToken])->assertNotFound();
        $this->getJson('/api/onboarding/session', ['X-Invite-Token' => $newToken])->assertOk();
    }

    public function test_revoke_kills_the_link(): void
    {
        $invite = FamilyInvite::factory()->withToken('t')->create(['kinder_id' => $this->kinder->id]);

        $this->postJson("/api/admin/family-invites/{$invite->id}/revoke")->assertOk()->assertJsonPath('status', 'revoked');

        $this->getJson('/api/onboarding/session', ['X-Invite-Token' => 't'])->assertNotFound();
    }

    public function test_submitted_invites_cannot_be_regenerated_or_revoked(): void
    {
        $invite = FamilyInvite::factory()->submitted()->create(['kinder_id' => $this->kinder->id]);

        $this->postJson("/api/admin/family-invites/{$invite->id}/regenerate")->assertUnprocessable();
        $this->postJson("/api/admin/family-invites/{$invite->id}/revoke")->assertUnprocessable();
    }

    public function test_other_kinders_invites_404(): void
    {
        $foreign = FamilyInvite::factory()->create();

        $this->postJson("/api/admin/family-invites/{$foreign->id}/revoke")->assertNotFound();
        $this->postJson("/api/admin/family-invites/{$foreign->id}/regenerate")->assertNotFound();
        $this->getJson('/api/admin/family-invites')->assertOk()->assertJsonCount(0);
    }

    public function test_expired_status_is_derived(): void
    {
        FamilyInvite::factory()->expired()->create(['kinder_id' => $this->kinder->id]);

        $this->getJson('/api/admin/family-invites')->assertJsonPath('0.status', 'expired');
    }

    public function test_only_the_director_can_manage_invites(): void
    {
        Sanctum::actingAs(AdminUser::factory()->create([
            'kinder_id' => $this->kinder->id,
            'type' => AdminUserType::Professor,
        ]), ['*']);

        $this->getJson('/api/admin/family-invites')->assertForbidden();
        $this->postJson('/api/admin/family-invites', ['label' => 'x'])->assertForbidden();
    }
}
