<?php

namespace Database\Factories;

use App\Enums\FamilyInviteStatus;
use App\Models\FamilyInvite;
use App\Models\Kinder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyInvite>
 */
class FamilyInviteFactory extends Factory
{
    protected $model = FamilyInvite::class;

    public function definition(): array
    {
        return [
            'kinder_id' => Kinder::factory(),
            'token_hash' => FamilyInvite::hashToken(FamilyInvite::generateToken()),
            'label' => 'Familia '.fake()->lastName(),
            'phone' => null,
            'status' => FamilyInviteStatus::Pending,
            'expires_at' => now()->addDays(FamilyInvite::DEFAULT_VALID_DAYS),
            'revision' => 0,
        ];
    }

    /**
     * Creates the invite and returns it with a known plain token for HTTP tests.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn () => ['token_hash' => FamilyInvite::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => FamilyInviteStatus::Revoked]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => FamilyInviteStatus::Submitted, 'submitted_at' => now()]);
    }
}
