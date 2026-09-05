<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `active_academic_year_slot` is a generated column used purely to enforce "at most one
     * draft/open campaign per target academic year" via a unique index — it collapses to NULL
     * for closed/archived campaigns, and MySQL doesn't deduplicate NULLs, so those never collide.
     */
    public function up(): void
    {
        Schema::create('pre_enrollment_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['draft', 'open', 'closed', 'archived'])->default('draft');
            $table->date('due_date')->nullable();
            $table->boolean('enforce_due_date')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('pre_enrollment_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('active_academic_year_slot')
                ->nullable()
                ->virtualAs("IF(status in ('draft','open'), academic_year_id, NULL)");
        });

        Schema::table('pre_enrollment_campaigns', function (Blueprint $table) {
            $table->unique('active_academic_year_slot', 'pre_enrollment_campaigns_active_year_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_campaigns');
    }
};
