<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->enum('schedule', ['inPerson', 'alternating', 'daycare']);
            $table->decimal('enrollment_fee_amount', 10, 2);
            $table->decimal('monthly_fee_amount', 10, 2);
            $table->string('uniform_size');
            $table->integer('uniform_qty_shirt');
            $table->integer('uniform_qty_short');
            $table->boolean('doc_birth_cert')->default(false);
            $table->boolean('doc_vaccine_card')->default(false);
            $table->boolean('doc_mother_id')->default(false);
            $table->boolean('doc_father_id')->default(false);
            $table->boolean('doc_authorized_ids')->default(false);
            $table->boolean('doc_photos')->default(false);
            $table->boolean('doc_service_contract')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
