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
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->enum('role', ['mother', 'father', 'other']);
            $table->string('name');
            $table->string('last_name');
            $table->string('nationality');
            $table->string('national_id');
            $table->integer('age');
            $table->string('civil_status');
            $table->string('religion')->nullable();
            $table->string('education');
            $table->string('profession');
            $table->string('workplace');
            $table->string('cell_phone');
            $table->string('work_phone')->nullable();
            $table->boolean('lives_with_child')->default(true);
            $table->string('address');
            $table->string('email');
            $table->boolean('uses_whatsapp')->default(false);
            $table->boolean('uses_facebook')->default(false);
            $table->boolean('uses_instagram')->default(false);
            $table->boolean('uses_threads')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
