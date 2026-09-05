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
        Schema::create('pre_enrollment_family_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('pre_enrollment_campaigns')->cascadeOnDelete();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->json('draft_payload')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'family_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_family_drafts');
    }
};
