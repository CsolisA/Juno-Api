<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per student per campaign. `exclusion_reason` is a fixed catalog (never free text)
     * so the director gets a reportable "who wasn't invited and why" — `exclusion_reason_detail`
     * is the only free-text field, and only meaningful when the reason is "other". This reason is
     * never surfaced to families; only admin-side code should ever read it.
     */
    public function up(): void
    {
        Schema::create('pre_enrollment_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('pre_enrollment_campaigns')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->foreignId('current_grade_id')->constrained('grades')->restrictOnDelete();
            $table->foreignId('current_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignId('target_grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->boolean('target_grade_overridden')->default(false);
            $table->foreignId('target_grade_overridden_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('target_grade_overridden_at')->nullable();
            $table->enum('status', ['excluded', 'pending', 'in_progress', 'submitted', 'approved', 'applied', 'not_submitted'])
                ->default('pending');
            $table->boolean('is_excluded')->default(false);
            $table->enum('exclusion_reason', ['pending_debt', 'behavior', 'confirmed_withdrawal', 'other', 'graduating'])->nullable();
            $table->text('exclusion_reason_detail')->nullable();
            $table->foreignId('excluded_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('excluded_at')->nullable();
            $table->json('draft_payload')->nullable();
            $table->json('submitted_snapshot')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->text('family_notes')->nullable();
            $table->boolean('has_reported_issue')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('projected_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->timestamps();
            $table->unique(['campaign_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_forms');
    }
};
