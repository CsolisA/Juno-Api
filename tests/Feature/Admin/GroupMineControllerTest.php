<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminUserType;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupMineControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_sees_their_own_active_group_with_roster(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->activo()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $academicYear->id,
            'professor_id' => $professor->id,
        ]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        Sanctum::actingAs($professor, ['*']);

        $response = $this->getJson('/api/admin/groups/me');

        $response->assertOk();
        $response->assertJsonPath('id', $group->id);
        $response->assertJsonCount(1, 'students');
        $response->assertJsonPath('students.0.id', $student->id);
    }

    public function test_assistant_sees_their_own_active_group(): void
    {
        $kinder = Kinder::factory()->create();
        $assistant = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Assistant]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->activo()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $academicYear->id,
            'assistant_id' => $assistant->id,
        ]);

        Sanctum::actingAs($assistant, ['*']);

        $response = $this->getJson('/api/admin/groups/me');

        $response->assertOk();
        $response->assertJsonPath('id', $group->id);
    }

    public function test_returns_404_when_no_active_group_is_assigned(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $closedYear = AcademicYear::factory()->cerrado()->create(['kinder_id' => $kinder->id]);
        Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $closedYear->id,
            'professor_id' => $professor->id,
        ]);

        Sanctum::actingAs($professor, ['*']);

        $response = $this->getJson('/api/admin/groups/me');

        $response->assertStatus(404);
    }

    /**
     * Reproduces the real-world bug: a year whose dates span today but was never activated has
     * a real, active-enrolled roster yet shows no active group until a director activates it.
     */
    public function test_returns_404_when_the_year_has_enrollments_but_was_never_activated(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->planeacion()->create([
            'kinder_id' => $kinder->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(6),
        ]);
        $group = Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $academicYear->id,
            'professor_id' => $professor->id,
        ]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson('/api/admin/groups/me')->assertStatus(404);

        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        Sanctum::actingAs($director, ['*']);
        $this->postJson("/api/admin/academic-years/{$academicYear->id}/activate")->assertOk();

        Sanctum::actingAs($professor, ['*']);
        $this->getJson('/api/admin/groups/me')->assertOk()->assertJsonPath('id', $group->id);
    }

    public function test_professor_cannot_list_all_groups(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $academicYear = AcademicYear::factory()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson("/api/admin/groups?academicYear={$academicYear->id}")->assertStatus(403);
    }

    public function test_professor_cannot_view_an_arbitrary_group_detail(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson("/api/admin/groups/{$group->id}")->assertStatus(403);
    }

    public function test_professor_cannot_update_a_group(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->planeacion()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->patchJson("/api/admin/groups/{$group->id}", ['professorId' => $professor->id])->assertStatus(403);
    }

    public function test_professor_cannot_reassign_a_students_group(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->patchJson("/api/admin/students/{$student->id}/group", ['groupId' => $group->id])->assertStatus(403);
    }
}
