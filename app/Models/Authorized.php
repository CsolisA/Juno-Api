<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name', 'last_name', 'last_name_two', 'phone', 'photo', 'relationship',
    'pick_up', 'communication', 'is_emergency_contact', 'occupation',
    'lives_with_child', 'province', 'canton', 'address', 'status',
])]
class Authorized extends Model
{
    protected $table = 'authorized';

    protected function casts(): array
    {
        return [
            'pick_up' => 'boolean',
            'communication' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'lives_with_child' => 'boolean',
            'status' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'authorized_student');
    }
}
