<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Costa Rica's provinces/cantons are structural reference data (7 provinces, ~84 cantons),
     * not user-managed content — `students.province`/`canton` and `authorized.province`/`canton`
     * stay free text for now, but the family/admin pre-enrollment forms need a real cascading
     * catalog (province -> canton) instead of unconstrained strings.
     */
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('display_order');
        });

        Schema::create('cantons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('display_order');
            $table->unique(['province_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cantons');
        Schema::dropIfExists('provinces');
    }
};
