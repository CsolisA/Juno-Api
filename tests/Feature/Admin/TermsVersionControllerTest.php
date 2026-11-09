<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminUserType;
use App\Models\AdminUser;
use App\Models\Family;
use App\Models\TermsVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TermsVersionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(AdminUserType $type): AdminUser
    {
        $admin = AdminUser::factory()->create(['type' => $type]);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_director_uploads_a_pdf_and_gets_the_next_version(): void
    {
        Storage::fake(TermsVersion::DISK);
        $director = $this->actingAsAdmin(AdminUserType::Director);

        $first = $this->postJson('/api/admin/terms', ['file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf')])
            ->assertCreated()->assertJson(['version' => 1, 'originalName' => 'a.pdf', 'uploadedBy' => $director->name]);
        $second = $this->postJson('/api/admin/terms', ['file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf')])
            ->assertCreated()->assertJson(['version' => 2]);

        Storage::disk(TermsVersion::DISK)->assertExists('terms/terms-v1.pdf');
        Storage::disk(TermsVersion::DISK)->assertExists('terms/terms-v2.pdf');
        $this->assertSame(2, TermsVersion::current()->version);
        $this->assertSame($director->id, TermsVersion::current()->uploaded_by);

        $this->getJson('/api/admin/terms')->assertOk()->assertJsonPath('0.version', 2)->assertJsonPath('1.version', 1);
    }

    public function test_only_pdfs_are_accepted(): void
    {
        Storage::fake(TermsVersion::DISK);
        $this->actingAsAdmin(AdminUserType::Director);

        $this->postJson('/api/admin/terms', [])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->postJson('/api/admin/terms', ['file' => UploadedFile::fake()->create('a.png', 10, 'image/png')])
            ->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, TermsVersion::count());
    }

    public function test_non_directors_cannot_upload_or_list(): void
    {
        Storage::fake(TermsVersion::DISK);

        foreach ([AdminUserType::Professor, AdminUserType::Assistant] as $type) {
            $this->actingAsAdmin($type);

            $this->postJson('/api/admin/terms', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();
            $this->getJson('/api/admin/terms')->assertForbidden();
        }

        $this->assertSame(0, TermsVersion::count());
    }

    public function test_family_cannot_use_the_admin_endpoints(): void
    {
        Sanctum::actingAs(Family::factory()->create(), ['*']);

        $this->getJson('/api/admin/terms')->assertForbidden();
    }

    public function test_family_sees_current_terms_and_what_they_accepted(): void
    {
        $old = TermsVersion::factory()->create();
        $family = Family::factory()->create(['terms_accepted_at' => now()->subDay(), 'terms_version' => (string) $old->version]);
        $new = TermsVersion::factory()->create();
        Sanctum::actingAs($family, ['*']);

        $this->getJson('/api/family/terms')
            ->assertOk()
            ->assertJsonPath('current.version', $new->version)
            ->assertJsonPath('accepted.version', $old->version)
            ->assertJsonPath('accepted.pdfUrl', url("/api/terms/{$old->version}/pdf"));
    }

    public function test_family_that_never_accepted_has_no_acceptance(): void
    {
        TermsVersion::factory()->create();
        Sanctum::actingAs(Family::factory()->create(), ['*']);

        $this->getJson('/api/family/terms')->assertOk()->assertJsonPath('accepted', null);
    }
}
