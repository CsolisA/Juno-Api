<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The columns dropped here were copied onto `enrollments` in the previous migration —
     * `students` keeps only stable identity fields, since transport/health/activity data is
     * now reviewed annually per enrollment rather than living as one mutable field on the student.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'transport_type', 'medical_conditions', 'diagnosis', 'takes_medication',
                'medication_details', 'plays_sport', 'sport_details',
                'has_extracurricular_classes', 'extracurricular_details',
            ]);
            $table->renameColumn('national_id', 'id_number');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('id_type', ['cedula', 'dimex', 'passport'])->default('cedula')->after('id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('id_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->renameColumn('id_number', 'national_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->enum('transport_type', ['minibus', 'family', 'other'])->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('diagnosis')->nullable();
            $table->boolean('takes_medication')->default(false);
            $table->string('medication_details')->nullable();
            $table->boolean('plays_sport')->default(false);
            $table->string('sport_details')->nullable();
            $table->boolean('has_extracurricular_classes')->default(false);
            $table->string('extracurricular_details')->nullable();
        });
    }
};
