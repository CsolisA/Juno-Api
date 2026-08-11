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
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kinder_id')->constrained('kinders')->cascadeOnDelete();
            $table->string('last_name_one');
            $table->string('last_name_two');
            $table->string('user')->unique();
            $table->string('password');
            $table->string('about_us')->nullable();
            $table->enum('referral_source', ['facebook', 'instagram', 'other'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
