<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'display_order'])]
class Province extends Model
{
    public $timestamps = false;

    /**
     * @return HasMany<Canton, $this>
     */
    public function cantons(): HasMany
    {
        return $this->hasMany(Canton::class);
    }
}
