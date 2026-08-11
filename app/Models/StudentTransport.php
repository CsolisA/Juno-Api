<?php

namespace App\Models;

use App\Enums\TransportType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'type', 'name', 'national_id', 'cell_phone'])]
class StudentTransport extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => TransportType::class,
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
