<?php

namespace Tests\Feature\Onboarding;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\FamilyInviteStatus;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\FamilyPasswordReset;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Student;
use App\Notifications\FamilyWelcomeNotification;
use Illuminate\Support\Facades\Notification;

class OnboardingSubmitTest extends OnboardingTestCase
{
    public function test_submit_creates_family_guardians_students_and_projected_enrollments(): void
    {
        Notification::fake();
        $invite = $this->invite();

        $secondGrade = Grade::factory()->create();
        $secondGroup = Group::factory()->create(['grade_id' => $secondGrade->id, 'academic_year_id' => $this->year->id]);

        $payload = $this->payload();
        $payload['students'][] = [
            'name' => 'Sofía',
            'lastName' => 'Pérez',
            'lastNameTwo' => 'Mora',
            'idNumber' => '3-3333-3333',
            'birthDate' => '2022-01-01',
            'nationality' => 'Costarricense',
            'province' => 'San José',
            'canton' => 'Escazú',
            'address' => 'San José',
            'gradeId' => $secondGrade->id,
        ];

        $response = $this->postJson('/api/onboarding/submit', $payload, $this->tokenHeader())->assertCreated();

        $family = Family::firstOrFail();
        $response->assertJson(['user' => $family->user, 'students' => 2]);
        $this->assertSame('Pérez', $family->last_name_one);
        $this->assertSame(1, $family->guardians()->count());
        $this->assertTrue($family->guardians()->first()->is_primary_contact);
        $this->assertSame(2, $family->students()->count());

        $luis = Student::where('id_number', '2-2222-2222')->firstOrFail();
        $enrollment = Enrollment::where('student_id', $luis->id)->firstOrFail();
        $this->assertSame($this->year->id, $enrollment->academic_year_id);
        $this->assertSame($this->grade->id, $enrollment->grade_id);
        $this->assertSame($this->group->id, $enrollment->group_id);
        $this->assertSame(EnrollmentStatus::Projected, $enrollment->status);
        $this->assertSame(EnrollmentSource::Manual, $enrollment->source);
        $this->assertSame($this->schedule->id, $enrollment->schedule_id);
        $this->assertNull($enrollment->monthly_fee_amount);
        $this->assertTrue($luis->groups->contains($this->group));

        $sofia = Student::where('id_number', '3-3333-3333')->firstOrFail();
        $this->assertSame($secondGroup->id, Enrollment::where('student_id', $sofia->id)->value('group_id'));
        $this->assertTrue($sofia->groups->contains($secondGroup));

        $invite->refresh();
        $this->assertSame(FamilyInviteStatus::Submitted, $invite->status);
        $this->assertSame($family->id, $invite->family_id);
        $this->assertNotNull($invite->submitted_at);

        $this->assertSame(1, FamilyPasswordReset::where('family_id', $family->id)->count());
        Notification::assertSentOnDemand(FamilyWelcomeNotification::class);
    }

    public function test_token_is_single_use(): void
    {
        Notification::fake();
        $this->invite();

        $this->postJson('/api/onboarding/submit', $this->payload(), $this->tokenHeader())->assertCreated();

        $second = $this->payload();
        $second['students'][0]['idNumber'] = '9-9999-9999';
        $this->postJson('/api/onboarding/submit', $second, $this->tokenHeader())->assertNotFound();
        $this->getJson('/api/onboarding/session', $this->tokenHeader())->assertNotFound();

        $this->assertSame(1, Family::count());
    }

    public function test_unknown_expired_and_revoked_tokens_all_404(): void
    {
        $this->getJson('/api/onboarding/session', $this->tokenHeader('nope'))->assertNotFound();
        $this->getJson('/api/onboarding/session')->assertNotFound();

        $this->invite(['status' => FamilyInviteStatus::Revoked]);
        $this->getJson('/api/onboarding/session', $this->tokenHeader())->assertNotFound();
    }

    public function test_expired_token_404s(): void
    {
        $this->invite(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/onboarding/submit', $this->payload(), $this->tokenHeader())->assertNotFound();
        $this->assertSame(0, Family::count());
    }

    public function test_duplicate_student_id_number_is_rejected_with_generic_message(): void
    {
        $this->invite();
        Student::factory()->create(['id_number' => '2-2222-2222']);

        $this->postJson('/api/onboarding/submit', $this->payload(), $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['students.0.idNumber']);

        $this->assertSame(0, Family::count());
    }

    public function test_requires_a_mother_or_father(): void
    {
        $this->invite();

        $payload = $this->payload(['guardians' => [['role' => 'other']]]);

        $this->postJson('/api/onboarding/submit', $payload, $this->tokenHeader())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['guardians']);

        $this->assertSame(0, Family::count());
    }

    public function test_failure_midway_rolls_everything_back_and_keeps_the_token_usable(): void
    {
        $orphanGrade = Grade::factory()->create(); // no 2027 group for this level
        $invite = $this->invite();

        $payload = $this->payload();
        $payload['students'][0]['gradeId'] = $orphanGrade->id;

        $this->postJson('/api/onboarding/submit', $payload, $this->tokenHeader())->assertStatus(503);

        $this->assertSame(0, Family::count());
        $this->assertSame(0, Student::count());
        $this->assertSame(FamilyInviteStatus::Pending, $invite->fresh()->status);
    }

    public function test_missing_onboarding_year_returns_503(): void
    {
        config(['app.onboarding_year' => 2099]);
        $this->invite();

        $this->postJson('/api/onboarding/submit', $this->payload(), $this->tokenHeader())->assertStatus(503);
        $this->assertSame(0, Family::count());
    }

    public function test_session_returns_label_draft_and_branding(): void
    {
        $this->invite(['label' => 'Familia Pérez']);

        $this->getJson('/api/onboarding/session', $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('label', 'Familia Pérez')
            ->assertJsonPath('revision', 0)
            ->assertJsonPath('kinder.name', $this->kinder->name);
    }

    public function test_draft_autosave_bumps_revision_and_rejects_stale_revision(): void
    {
        $this->invite();

        $this->patchJson('/api/onboarding/draft', ['revision' => 0, 'draft' => ['guardians' => [['name' => 'Ana']]]], $this->tokenHeader())
            ->assertOk()
            ->assertJsonPath('revision', 1);

        $this->patchJson('/api/onboarding/draft', ['revision' => 0, 'draft' => ['guardians' => []]], $this->tokenHeader())
            ->assertStatus(409)
            ->assertJsonPath('revision', 1)
            ->assertJsonPath('draft.guardians.0.name', 'Ana');

        $this->getJson('/api/onboarding/session', $this->tokenHeader())
            ->assertJsonPath('draft.guardians.0.name', 'Ana');
    }

    public function test_catalogs_are_public(): void
    {
        $this->getJson('/api/onboarding/catalogs/grades')->assertOk();
        $this->getJson('/api/onboarding/catalogs/provinces')->assertOk();
        $this->getJson('/api/onboarding/catalogs/nationalities')->assertOk();
    }
}
