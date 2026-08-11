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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name');
            $table->string('last_name_two');
            $table->foreignId('family_id')->constrained('families')->cascadeOnDelete();
            $table->enum('transport_type', ['minibus', 'family', 'other']);
            $table->string('national_id')->unique();
            $table->date('birth_date');
            $table->string('insurance_policy_number')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('nationality');
            $table->string('province');
            $table->string('canton');
            $table->string('address');
            $table->string('phone')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->text('diagnosis')->nullable();
            $table->boolean('takes_medication')->default(false);
            $table->string('medication_details')->nullable();
            $table->boolean('plays_sport')->default(false);
            $table->string('sport_details')->nullable();
            $table->boolean('has_extracurricular_classes')->default(false);
            $table->string('extracurricular_details')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
