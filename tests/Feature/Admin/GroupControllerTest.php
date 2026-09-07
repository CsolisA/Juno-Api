<?php

namespace Tests\Feature\Admin;

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

class GroupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_groups_with_student_counts_for_the_year(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/api/admin/groups?academicYear={$academicYear->id}");

        $response->assertOk();
        $response->assertJsonPath('0.id', $group->id);
        $response->assertJsonPath('0.studentsCount', 1);
    }

    public function test_show_returns_full_roster(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/api/admin/groups/{$group->id}");

        $response->assertOk();
        $response->assertJsonPath('grade.id', $grade->id);
        $response->assertJsonPath('professor.id', $group->professor_id);
        $response->assertJsonCount(1, 'students');
        $response->assertJsonPath('students.0.id', $student->id);
    }

    public function test_update_is_rejected_when_academic_year_is_closed(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->cerrado()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $newProfessor = AdminUser::factory()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/api/admin/groups/{$group->id}", ['professorId' => $newProfessor->id]);

        $response->assertStatus(422);
        $this->assertNotSame($newProfessor->id, $group->fresh()->professor_id);
    }

    public function test_update_assigns_professor_and_assistant_when_year_is_open(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->planeacion()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $newProfessor = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $newAssistant = AdminUser::factory()->create(['kinder_id' => $kinder->id]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/api/admin/groups/{$group->id}", [
            'professorId' => $newProfessor->id,
            'assistantId' => $newAssistant->id,
        ]);

        $response->assertOk();
        $group->refresh();
        $this->assertSame($newProfessor->id, $group->professor_id);
        $this->assertSame($newAssistant->id, $group->assistant_id);
    }
}
