<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Split into its own migration because `pre_enrollment_forms` (created after `enrollments`)
     * couldn't otherwise be referenced without a circular table dependency.
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('pre_enrollment_form_id')
                ->nullable()
                ->after('source')
                ->constrained('pre_enrollment_forms')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pre_enrollment_form_id');
        });
    }
};
