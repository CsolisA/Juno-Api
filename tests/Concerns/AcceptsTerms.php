<?php

namespace Tests\Concerns;

use App\Models\TermsVersion;

trait AcceptsTerms
{
    protected function currentTerms(): TermsVersion
    {
        return TermsVersion::current() ?? TermsVersion::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function termsPayload(array $overrides = []): array
    {
        return array_replace(['termsAccepted' => true, 'termsVersion' => $this->currentTerms()->version], $overrides);
    }
}
