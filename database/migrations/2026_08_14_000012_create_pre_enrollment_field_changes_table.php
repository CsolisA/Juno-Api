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
     * Append-only audit log (no `updated_at`). Points to exactly one of `pre_enrollment_forms`
     * or `pre_enrollment_family_drafts` — two nullable FKs plus a CHECK constraint rather than a
     * polymorphic relation, since there's no polymorphic-relation precedent in this codebase and
     * this only ever needs to point at one of two known tables.
     */
    public function up(): void
    {
        Schema::create('pre_enrollment_field_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->nullable()->constrained('pre_enrollment_forms')->cascadeOnDelete();
            $table->foreignId('family_draft_id')->nullable()->constrained('pre_enrollment_family_drafts')->cascadeOnDelete();
            $table->string('field_path');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->enum('actor_type', ['family', 'admin']);
            $table->unsignedBigInteger('actor_id');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('
            ALTER TABLE pre_enrollment_field_changes
            ADD CONSTRAINT chk_field_changes_exactly_one_parent
            CHECK ((form_id IS NOT NULL AND family_draft_id IS NULL) OR (form_id IS NULL AND family_draft_id IS NOT NULL))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_field_changes');
    }
};
