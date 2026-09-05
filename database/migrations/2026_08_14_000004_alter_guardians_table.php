<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reconciles the existing `guardians` table (added ahead of this feature, for family-portal
     * password-reset scoping) with the pre-enrollment form's field set: a few columns are renamed
     * for consistency, `age` is replaced with `birth_date` (age is computed, per spec), and
     * `last_name` splits into `last_name_one`/`last_name_two` to match `students`/`families`.
     */
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->renameColumn('national_id', 'id_number');
            $table->renameColumn('civil_status', 'marital_status');
            $table->renameColumn('education', 'education_level');
            $table->renameColumn('profession', 'occupation');
            $table->renameColumn('cell_phone', 'mobile_phone');
            $table->renameColumn('last_name', 'last_name_one');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('last_name_two')->nullable()->after('last_name_one');
            $table->enum('id_type', ['cedula', 'dimex', 'passport'])->default('cedula')->after('id_number');
            $table->date('birth_date')->nullable()->after('id_type');
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('status')->default(true);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('age');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table) {
            $table->integer('age')->default(0);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn(['last_name_two', 'id_type', 'birth_date', 'is_primary_contact', 'status']);
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->renameColumn('id_number', 'national_id');
            $table->renameColumn('marital_status', 'civil_status');
            $table->renameColumn('education_level', 'education');
            $table->renameColumn('occupation', 'profession');
            $table->renameColumn('mobile_phone', 'cell_phone');
            $table->renameColumn('last_name_one', 'last_name');
        });
    }
};
