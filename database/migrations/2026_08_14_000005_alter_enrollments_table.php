<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Moves the year-specific fields the pre-enrollment spec wants reviewed annually
     * (transport, health, activities) from `students` onto `enrollment`, and normalizes the
     * `schedule` enum into a `schedule_id` FK. Fees/uniform/group become nullable because a
     * "projected" enrollment created from an approved pre-enrollment form won't have those
     * assigned yet — they're filled in by the director afterwards.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable()->after('group_id')->constrained('grades')->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->after('schedule')->constrained('schedules')->restrictOnDelete();
            $table->enum('status', ['projected', 'active', 'withdrawn', 'graduated'])->default('active')->after('schedule_id');
            $table->enum('source', ['pre_enrollment', 'manual'])->default('manual')->after('status');
            $table->enum('transport_type', ['minibus', 'family', 'other'])->nullable()->after('source');
            $table->text('medical_conditions')->nullable();
            $table->text('diagnosis')->nullable();
            $table->boolean('takes_medication')->default(false);
            $table->string('medication_details')->nullable();
            $table->boolean('practices_sport')->default(false);
            $table->string('sport_details')->nullable();
            $table->boolean('extra_classes')->default(false);
            $table->string('extra_classes_detail')->nullable();
        });

        DB::statement('UPDATE enrollments e JOIN `groups` g ON g.id = e.group_id SET e.grade_id = g.grade_id');

        $scheduleIdsByCode = DB::table('schedules')->pluck('id', 'code');
        DB::table('enrollments')->where('schedule', 'inPerson')->update(['schedule_id' => $scheduleIdsByCode['PRESENCIAL']]);
        DB::table('enrollments')->where('schedule', 'alternating')->update(['schedule_id' => $scheduleIdsByCode['ALTERNO']]);
        DB::table('enrollments')->where('schedule', 'daycare')->update(['schedule_id' => $scheduleIdsByCode['GUARDERIA']]);

        DB::statement('
            UPDATE enrollments e JOIN students s ON s.id = e.student_id
            SET e.transport_type = s.transport_type,
                e.medical_conditions = s.medical_conditions,
                e.diagnosis = s.diagnosis,
                e.takes_medication = s.takes_medication,
                e.medication_details = s.medication_details,
                e.practices_sport = s.plays_sport,
                e.sport_details = s.sport_details,
                e.extra_classes = s.has_extracurricular_classes,
                e.extra_classes_detail = s.extracurricular_details
        ');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('grade_id')->nullable(false)->change();
            $table->foreignId('schedule_id')->nullable(false)->change();
            $table->foreignId('group_id')->nullable()->change();
            $table->decimal('enrollment_fee_amount', 10, 2)->nullable()->change();
            $table->decimal('monthly_fee_amount', 10, 2)->nullable()->change();
            $table->string('uniform_size')->nullable()->change();
            $table->integer('uniform_qty_shirt')->nullable()->change();
            $table->integer('uniform_qty_short')->nullable()->change();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('schedule', ['inPerson', 'alternating', 'daycare'])->nullable()->after('group_id');
        });

        DB::statement("UPDATE enrollments e JOIN schedules s ON s.id = e.schedule_id SET e.schedule = CASE s.code WHEN 'PRESENCIAL' THEN 'inPerson' WHEN 'ALTERNO' THEN 'alternating' WHEN 'GUARDERIA' THEN 'daycare' END");

        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('schedule', ['inPerson', 'alternating', 'daycare'])->nullable(false)->change();
            $table->foreignId('group_id')->nullable(false)->change();
            $table->decimal('enrollment_fee_amount', 10, 2)->nullable(false)->change();
            $table->decimal('monthly_fee_amount', 10, 2)->nullable(false)->change();
            $table->string('uniform_size')->nullable(false)->change();
            $table->integer('uniform_qty_shirt')->nullable(false)->change();
            $table->integer('uniform_qty_short')->nullable(false)->change();
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_id');
            $table->dropConstrainedForeignId('schedule_id');
            $table->dropColumn([
                'status', 'source', 'transport_type', 'medical_conditions', 'diagnosis',
                'takes_medication', 'medication_details', 'practices_sport', 'sport_details',
                'extra_classes', 'extra_classes_detail',
            ]);
        });
    }
};
