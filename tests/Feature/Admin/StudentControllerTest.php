<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminUserType;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Authorized;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Kinder;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_splits_active_and_inactive_students(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);

        $activeStudent = Student::factory()->create(['family_id' => $family->id, 'name' => 'Ana']);
        Enrollment::factory()->create([
            'student_id' => $activeStudent->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $withdrawnStudent = Student::factory()->create(['family_id' => $family->id, 'name' => 'Beto']);
        Enrollment::factory()->create([
            'student_id' => $withdrawnStudent->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
            'status' => EnrollmentStatus::Withdrawn,
        ]);

        Sanctum::actingAs($director, ['*']);

        $active = $this->getJson('/api/admin/students?status=active')->assertOk();
        $active->assertJsonCount(1);
        $active->assertJsonPath('0.id', $activeStudent->id);

        $inactive = $this->getJson('/api/admin/students?status=inactive')->assertOk();
        $inactive->assertJsonCount(1);
        $inactive->assertJsonPath('0.id', $withdrawnStudent->id);
    }

    public function test_index_is_director_only(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson('/api/admin/students')->assertStatus(403);
    }

    public function test_director_sees_the_complete_file(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $grade = Grade::factory()->create();
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        Guardian::factory()->create(['family_id' => $family->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
            'status' => EnrollmentStatus::Active,
        ]);
        $contact = Authorized::create([
            'name' => 'Tía', 'last_name' => 'Rojas', 'last_name_two' => 'Mora',
            'phone' => '8888-0000', 'relationship' => 'tía', 'pick_up' => true,
            'is_emergency_contact' => true, 'province' => 'San José', 'canton' => 'Escazú', 'address' => 'Calle 1',
        ]);
        $student->authorizedPersons()->attach($contact->id);

        Sanctum::actingAs($director, ['*']);

        $response = $this->getJson("/api/admin/students/{$student->id}")->assertOk();

        $response->assertJsonPath('idNumber', $student->id_number);
        $response->assertJsonPath('status', 'active');
        $response->assertJsonCount(1, 'guardians');
        $response->assertJsonPath('guardians.0.idNumber', fn ($value) => $value !== null);
        $response->assertJsonCount(1, 'authorizedContacts');
        $response->assertJsonPath('currentEnrollment.documents.birthCert', false);
        $response->assertJsonCount(1, 'enrollmentHistory');
    }

    public function test_director_can_edit_a_student(): void
    {
        $kinder = Kinder::factory()->create();
        $director = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Director]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);

        Sanctum::actingAs($director, ['*']);

        $response = $this->patchJson("/api/admin/students/{$student->id}", [
            'phone' => '8888-1234',
            'bloodType' => 'O+',
        ]);

        $response->assertOk();
        $this->assertSame('8888-1234', $student->fresh()->phone);
        $this->assertSame('O+', $student->fresh()->blood_type);
    }

    public function test_professor_can_see_basic_info_for_a_student_in_their_active_group(): void
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
        Guardian::factory()->create(['family_id' => $family->id]);
        $student = Student::factory()->create(['family_id' => $family->id, 'blood_type' => 'A+']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
            'status' => EnrollmentStatus::Active,
        ]);

        Sanctum::actingAs($professor, ['*']);

        $response = $this->getJson("/api/admin/students/{$student->id}")->assertOk();

        $response->assertJsonPath('bloodType', 'A+');
        $response->assertJsonMissingPath('idNumber');
        $response->assertJsonMissingPath('family');
        $response->assertJsonMissingPath('enrollmentHistory');
    }

    public function test_professor_cannot_see_a_student_outside_their_active_group(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->getJson("/api/admin/students/{$student->id}")->assertStatus(403);
    }

    public function test_professor_cannot_edit_a_student(): void
    {
        $kinder = Kinder::factory()->create();
        $professor = AdminUser::factory()->create(['kinder_id' => $kinder->id, 'type' => AdminUserType::Professor]);
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);

        Sanctum::actingAs($professor, ['*']);

        $this->patchJson("/api/admin/students/{$student->id}", ['phone' => '8888-9999'])->assertStatus(403);
    }
}
