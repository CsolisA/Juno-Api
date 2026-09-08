<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Approval is family-granular: a director approves every one of a family's submitted
     * children in one action, so the "aprobado" state lives here rather than on individual
     * pre_enrollment_forms rows.
     */
    public function up(): void
    {
        Schema::table('pre_enrollment_family_drafts', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pre_enrollment_family_drafts', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'approved_by', 'reopened_at']);
        });
    }
};
