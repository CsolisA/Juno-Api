<?php

namespace Database\Factories;

use App\Models\TermsVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TermsVersion>
 */
class TermsVersionFactory extends Factory
{
    protected $model = TermsVersion::class;

    public function definition(): array
    {
        $version = (TermsVersion::max('version') ?? 0) + 1;

        return [
            'version' => $version,
            'path' => "terms/terms-v{$version}.pdf",
            'original_name' => 'terminos.pdf',
        ];
    }
}
