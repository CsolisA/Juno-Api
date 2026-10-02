<?php

namespace App\Models;

use App\Enums\FamilyInviteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'kinder_id', 'token_hash', 'label', 'phone', 'status', 'expires_at', 'created_by',
    'draft_payload', 'revision', 'family_id', 'submitted_at',
])]
class FamilyInvite extends Model
{
    use HasFactory;

    public const DEFAULT_VALID_DAYS = 14;

    protected function casts(): array
    {
        return [
            'status' => FamilyInviteStatus::class,
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'draft_payload' => 'array',
            'revision' => 'integer',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Only the hash is stored, so the plain token is returned to the admin exactly once.
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public static function findByToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::where('token_hash', static::hashToken($token))->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === FamilyInviteStatus::Pending && ! $this->isExpired();
    }

    /**
     * pending, submitted, revoked or expired — "expired" is derived, never stored.
     */
    public function displayStatus(): string
    {
        if ($this->status === FamilyInviteStatus::Pending && $this->isExpired()) {
            return 'expired';
        }

        return $this->status->value;
    }

    public static function urlFor(string $token): string
    {
        return rtrim(config('app.frontend_url', config('app.url')), '/').'/registro/'.$token;
    }

    /**
     * @return BelongsTo<Kinder, $this>
     */
    public function kinder(): BelongsTo
    {
        return $this->belongsTo(Kinder::class);
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
