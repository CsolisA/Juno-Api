<?php

namespace Tests\Feature;

use App\Models\TermsVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TermsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function storePdf(TermsVersion $terms): void
    {
        Storage::disk(TermsVersion::DISK)->put($terms->path, "%PDF-1.4\n%%EOF");
    }

    public function test_current_returns_latest_version_and_pdf_url(): void
    {
        TermsVersion::factory()->create();
        $latest = TermsVersion::factory()->create();

        $this->getJson('/api/terms/current')
            ->assertOk()
            ->assertJson(['version' => $latest->version, 'pdfUrl' => url("/api/terms/{$latest->version}/pdf")]);
    }

    public function test_current_is_404_when_nothing_was_uploaded(): void
    {
        $this->getJson('/api/terms/current')->assertNotFound();
    }

    public function test_pdf_is_served_inline_for_current_and_for_a_given_version(): void
    {
        Storage::fake(TermsVersion::DISK);
        $old = TermsVersion::factory()->create();
        $new = TermsVersion::factory()->create();
        $this->storePdf($old);
        $this->storePdf($new);

        foreach (['/api/terms/current/pdf', "/api/terms/{$old->version}/pdf"] as $url) {
            $response = $this->get($url)->assertOk();

            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        }
    }

    public function test_pdf_is_404_when_the_file_or_version_is_missing(): void
    {
        Storage::fake(TermsVersion::DISK);
        TermsVersion::factory()->create();

        $this->getJson('/api/terms/current/pdf')->assertNotFound();
        $this->getJson('/api/terms/99/pdf')->assertNotFound();
    }
}
