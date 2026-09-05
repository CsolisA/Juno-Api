<?php

namespace Database\Seeders;

use App\Enums\AdminUserType;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRole;
use App\Enums\TransportType;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Kinder;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const ADMIN_PASSWORD = 'Admin123';

    private const FAMILY_PASSWORD = 'Family123';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ScheduleSeeder::class,
            ProvinceCantonSeeder::class,
        ]);

        $kinder = Kinder::create([
            'name' => 'KSorpresita',
            'main_color' => '#f5191b',
            'second_color' => '#4ECDC4',
            'font_name' => 'FREDOKA',
        ]);

        $academicYear = AcademicYear::create([
            'kinder_id' => $kinder->id,
            'year' => now()->year,
            'start_date' => now()->year.'-02-01',
            'end_date' => now()->year.'-12-15',
        ]);

        $director = AdminUser::create([
            'kinder_id' => $kinder->id,
            'name' => 'Admin KSorpresita',
            'email' => 'admin@ksorpresita.com',
            'phone' => '8888-0000',
            'type' => AdminUserType::Director,
            'password' => self::ADMIN_PASSWORD,
            'emergency_phone' => '8888-0001',
            'emergency_name' => 'Contacto de Emergencia',
            'birth_date' => '1985-01-15',
            'hire_date' => '2020-01-06',
            'status' => true,
            'address' => 'San José, Costa Rica',
        ]);

        $levels = ['Maternal', 'Prekinder', 'Kinder', 'Preparatoria'];

        $groups = [];

        foreach ($levels as $index => $levelName) {
            $grade = Grade::create([
                'name' => $levelName,
                'order' => $index,
                'is_final' => $index === count($levels) - 1,
            ]);

            $teacher = AdminUser::create([
                'kinder_id' => $kinder->id,
                'name' => "Profesora {$levelName}",
                'email' => 'profe.'.strtolower($levelName).'@ksorpresita.test',
                'phone' => '8888-100'.$index,
                'type' => AdminUserType::Professor,
                'password' => self::ADMIN_PASSWORD,
                'emergency_phone' => '8888-200'.$index,
                'emergency_name' => 'Contacto de Emergencia',
                'birth_date' => '1990-03-10',
                'hire_date' => '2022-01-10',
                'status' => true,
                'address' => 'San José, Costa Rica',
            ]);

            $groups[$levelName] = Group::create([
                'grade_id' => $grade->id,
                'academic_year_id' => $academicYear->id,
                'professor_id' => $teacher->id,
            ]);
        }

        $this->call([
            ScheduleGradeSeeder::class,
        ]);

        $family = Family::create([
            'kinder_id' => $kinder->id,
            'last_name_one' => 'Rodríguez',
            'last_name_two' => 'Mora',
            'user' => 'rodmor',
            'password' => self::FAMILY_PASSWORD,
            'about_us' => 'Recomendación de otra familia',
            'referral_source' => 'other',
        ]);

        Guardian::create([
            'family_id' => $family->id,
            'role' => GuardianRole::Mother,
            'name' => 'María',
            'last_name_one' => 'Mora',
            'nationality' => 'Costarricense',
            'id_number' => '1-2345-6789',
            'birth_date' => '1994-04-02',
            'marital_status' => 'Casada',
            'education_level' => 'Universitaria',
            'occupation' => 'Ingeniera',
            'workplace' => 'Empresa Privada',
            'mobile_phone' => '8888-3000',
            'address' => 'San José, Costa Rica',
            'email' => 'maria.mora@example.test',
            'uses_whatsapp' => true,
            'is_primary_contact' => true,
        ]);

        $students = [
            [
                'name' => 'Emma',
                'last_name' => 'Rodríguez',
                'last_name_two' => 'Mora',
                'birth_date' => now()->subYears(3)->format('Y-m-d'),
                'id_number' => '0-0001-0001',
                'level' => 'Maternal',
            ],
            [
                'name' => 'Mateo',
                'last_name' => 'Rodríguez',
                'last_name_two' => 'Mora',
                'birth_date' => now()->subYears(5)->format('Y-m-d'),
                'id_number' => '0-0002-0002',
                'level' => 'Preparatoria',
            ],
        ];

        $presencialScheduleId = Schedule::where('code', 'PRESENCIAL')->value('id');

        foreach ($students as $studentData) {
            $group = $groups[$studentData['level']];

            $student = Student::create([
                'name' => $studentData['name'],
                'last_name' => $studentData['last_name'],
                'last_name_two' => $studentData['last_name_two'],
                'family_id' => $family->id,
                'id_number' => $studentData['id_number'],
                'birth_date' => $studentData['birth_date'],
                'nationality' => 'Costarricense',
                'province' => 'San José',
                'canton' => 'Escazú',
                'address' => 'San José, Costa Rica',
            ]);

            $student->groups()->attach($group->id);

            Enrollment::create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'group_id' => $group->id,
                'grade_id' => $group->grade_id,
                'schedule_id' => $presencialScheduleId,
                'status' => EnrollmentStatus::Active,
                'source' => EnrollmentSource::Manual,
                'transport_type' => TransportType::Family,
                'enrollment_fee_amount' => 300.00,
                'monthly_fee_amount' => 250.00,
                'uniform_size' => '4',
                'uniform_qty_shirt' => 2,
                'uniform_qty_short' => 2,
            ]);
        }

        $this->command?->info('KSorpresita seeded.');
        $this->command?->info("Admin login    -> email: {$director->email} | password: ".self::ADMIN_PASSWORD);
        $this->command?->info("Family login   -> user:  {$family->user} | password: ".self::FAMILY_PASSWORD);
    }
}
