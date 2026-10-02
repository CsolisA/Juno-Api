<?php

namespace Tests\Feature\Onboarding;

use App\Models\AcademicYear;
use App\Models\FamilyInvite;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class OnboardingTestCase extends TestCase
{
    use RefreshDatabase;

    protected const TOKEN = 'plain-test-token';

    protected Kinder $kinder;

    protected Grade $grade;

    protected Group $group;

    protected AcademicYear $year;

    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.onboarding_year' => 2027]);

        $this->kinder = Kinder::factory()->create();
        $this->grade = Grade::factory()->create();
        $this->schedule = Schedule::factory()->create();
        $this->year = AcademicYear::factory()->create(['kinder_id' => $this->kinder->id, 'year' => 2027]);
        $this->group = Group::factory()->create([
            'grade_id' => $this->grade->id,
            'academic_year_id' => $this->year->id,
        ]);
    }

    protected function invite(array $state = []): FamilyInvite
    {
        return FamilyInvite::factory()->withToken(self::TOKEN)->create($state + ['kinder_id' => $this->kinder->id]);
    }

    /**
     * @return array<string, string>
     */
    protected function tokenHeader(?string $token = self::TOKEN): array
    {
        return ['X-Invite-Token' => (string) $token];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'family' => ['aboutUs' => 'Un amigo', 'referralSource' => 'facebook'],
            'guardians' => [[
                'role' => 'mother',
                'name' => 'Ana',
                'lastNameOne' => 'Pérez',
                'lastNameTwo' => 'Mora',
                'nationality' => 'Costarricense',
                'idType' => 'cedula',
                'idNumber' => '1-1111-1111',
                'maritalStatus' => 'casado',
                'educationLevel' => 'universidad',
                'occupation' => 'Ingeniera',
                'workplace' => 'ACME',
                'mobilePhone' => '8888-8888',
                'address' => 'San José',
                'email' => 'ana@example.com',
            ]],
            'students' => [[
                'name' => 'Luis',
                'lastName' => 'Pérez',
                'lastNameTwo' => 'Mora',
                'idNumber' => '2-2222-2222',
                'birthDate' => '2023-03-01',
                'nationality' => 'Costarricense',
                'province' => 'San José',
                'canton' => 'Escazú',
                'address' => 'San José',
                'gradeId' => $this->grade->id,
            ]],
        ], $overrides);
    }
}
