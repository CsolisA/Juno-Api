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
        Schema::create('authorized', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name');
            $table->string('last_name_two');
            $table->string('phone');
            $table->string('photo')->nullable();
            $table->string('relationship');
            $table->boolean('pick_up')->default(false);
            $table->boolean('communication')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->string('occupation')->nullable();
            $table->boolean('lives_with_child')->default(false);
            $table->string('province');
            $table->string('canton');
            $table->string('address');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorized');
    }
};
