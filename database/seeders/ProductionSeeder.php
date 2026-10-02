<?php

namespace Database\Seeders;

use App\Enums\AcademicYearStatus;
use App\Enums\AdminUserType;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Grade;
use App\Models\Kinder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    /**
     * Minimal real data to start operating: reference data, the kinder, the current academic
     * year, the grades and the director. Idempotent, so it's safe to re-run.
     *
     * The director password is read from SEED_DIRECTOR_PASSWORD; when unset, a random one is
     * generated and printed once (only on the run that creates the director).
     */
    public function run(): void
    {
        $this->call([
            ScheduleSeeder::class,
            ProvinceCantonSeeder::class,
        ]);

        $kinder = Kinder::firstOrCreate(
            ['name' => 'KSorpresita'],
            [
                'main_color' => '#f5191b',
                'second_color' => '#4ECDC4',
                'font_name' => 'FREDOKA',
            ],
        );

        $year = now()->year;

        AcademicYear::firstOrCreate(
            ['kinder_id' => $kinder->id, 'year' => $year],
            [
                'start_date' => $year.'-02-01',
                'end_date' => $year.'-12-15',
                'status' => AcademicYearStatus::Activo,
            ],
        );

        $levels = ['Maternal', 'Prekinder', 'Kinder', 'Preparatoria'];

        foreach ($levels as $index => $levelName) {
            Grade::firstOrCreate(
                ['name' => $levelName],
                ['order' => $index, 'is_final' => $index === count($levels) - 1],
            );
        }

        $this->call(ScheduleGradeSeeder::class);

        $password = env('SEED_DIRECTOR_PASSWORD') ?: Str::password(16, symbols: false);

        $director = AdminUser::firstOrCreate(
            ['email' => 'info@ksorpresita.com'],
            [
                'kinder_id' => $kinder->id,
                'name' => 'Directora KSorpresita',
                'type' => AdminUserType::Director,
                'password' => $password,
                'status' => true,
                // NOT NULL columns; placeholders to be edited from the admin panel.
                'phone' => '0000-0000',
                'emergency_phone' => '0000-0000',
                'emergency_name' => 'Pendiente',
                'birth_date' => '1980-01-01',
                'hire_date' => now()->toDateString(),
                'address' => 'Pendiente',
            ],
        );

        if ($director->wasRecentlyCreated) {
            $this->command?->info("Director created -> email: {$director->email} | password: {$password}");
            $this->command?->warn('Change this password after the first login.');
        } else {
            $this->command?->info('Director already exists, left untouched.');
        }

        $this->call(Year2027Seeder::class);

        $this->command?->info('Production data seeded.');
    }
}
