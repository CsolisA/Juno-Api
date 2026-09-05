<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'order', 'is_final'])]
class Grade extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'is_final' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * The grade one level above this one, per `order` — null if none exists.
     */
    public function nextGrade(): ?self
    {
        return static::where('order', $this->order + 1)->first();
    }
}
