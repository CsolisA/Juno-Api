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
        Schema::table('pre_enrollment_forms', function (Blueprint $table) {
            $table->boolean('added_late')->default(false)->after('is_excluded');
            $table->timestamp('notified_at')->nullable()->after('added_late');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pre_enrollment_forms', function (Blueprint $table) {
            $table->dropColumn(['added_late', 'notified_at']);
        });
    }
};
