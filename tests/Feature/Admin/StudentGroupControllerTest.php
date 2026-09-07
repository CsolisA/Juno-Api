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

class StudentGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigns_student_to_a_new_group_in_the_same_year(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->create(['kinder_id' => $kinder->id]);
        $originalGroup = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $newGroup = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $originalGroup->id,
            'grade_id' => $grade->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/api/admin/students/{$student->id}/group", ['groupId' => $newGroup->id]);

        $response->assertOk();
        $this->assertSame($newGroup->id, $enrollment->fresh()->group_id);
    }

    public function test_reassignment_is_rejected_when_target_years_group_is_closed(): void
    {
        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->cerrado()->create(['kinder_id' => $kinder->id]);
        $originalGroup = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $newGroup = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $originalGroup->id,
            'grade_id' => $grade->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/api/admin/students/{$student->id}/group", ['groupId' => $newGroup->id]);

        $response->assertStatus(422);
        $this->assertSame($originalGroup->id, $enrollment->fresh()->group_id);
    }
}
