<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proof that a family accepted the terms PDF (and which version) when they submitted.
     */
    public function up(): void
    {
        Schema::table('family_invites', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('submitted_at');
            $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version', 32)->nullable();
        });

        Schema::table('pre_enrollment_family_drafts', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('submitted_at');
            $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
        });
    }

    public function down(): void
    {
        foreach (['family_invites', 'families', 'pre_enrollment_family_drafts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['terms_accepted_at', 'terms_version']);
            });
        }
    }
};
