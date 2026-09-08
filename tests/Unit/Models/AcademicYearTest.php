<?php

namespace Tests\Unit\Models;

use App\Models\AcademicYear;
use App\Models\Kinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_ignores_a_year_whose_dates_span_today_but_is_not_activo(): void
    {
        $kinder = Kinder::factory()->create();

        AcademicYear::factory()->planeacion()->create([
            'kinder_id' => $kinder->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(6),
        ]);

        $this->assertNull(AcademicYear::current($kinder->id));
    }

    public function test_current_returns_the_activo_year_regardless_of_its_dates(): void
    {
        $kinder = Kinder::factory()->create();

        $activeYear = AcademicYear::factory()->activo()->create([
            'kinder_id' => $kinder->id,
            'start_date' => now()->addYear(),
            'end_date' => now()->addYear()->addMonths(10),
        ]);

        $this->assertSame($activeYear->id, AcademicYear::current($kinder->id)?->id);
    }
}
