<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `active_status_slot` is a generated column used purely to enforce "at most one activo
     * academic year per kinder" via a unique index — same technique as
     * pre_enrollment_campaigns.active_academic_year_slot. It collapses to NULL for any year not
     * currently activo, and MySQL doesn't deduplicate NULLs, so those never collide.
     */
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->enum('status', ['planeacion', 'activo', 'cerrado'])->default('planeacion')->after('year');
            $table->boolean('campaign_confirmed')->nullable()->after('status');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->unsignedBigInteger('active_status_slot')
                ->nullable()
                ->virtualAs("IF(status = 'activo', kinder_id, NULL)");
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->unique('active_status_slot', 'academic_years_active_status_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropUnique('academic_years_active_status_unique');
            $table->dropColumn('active_status_slot');
            $table->dropColumn(['status', 'campaign_confirmed']);
        });
    }
};
