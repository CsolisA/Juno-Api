<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kinder_id')->constrained('kinders')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label');
            $table->string('phone')->nullable();
            $table->enum('status', ['pending', 'submitted', 'revoked'])->default('pending');
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->json('draft_payload')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_invites');
    }
};
