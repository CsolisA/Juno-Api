<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['version', 'path', 'original_name', 'uploaded_by'])]
class TermsVersion extends Model
{
    use HasFactory;

    public const DISK = 'local';

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public static function current(): ?self
    {
        return static::orderByDesc('version')->first();
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'uploaded_by');
    }

    public function pdfUrl(): string
    {
        return url("/api/terms/{$this->version}/pdf");
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'version' => $this->version,
            'pdfUrl' => $this->pdfUrl(),
            'uploadedAt' => $this->created_at->toIso8601String(),
        ];
    }
}
