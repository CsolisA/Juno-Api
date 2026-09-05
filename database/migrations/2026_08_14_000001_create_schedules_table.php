<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('days_per_week');
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('schedules')->insert([
            [
                'code' => 'PRESENCIAL',
                'name' => 'Presencial',
                'description' => '5 días a la semana',
                'start_time' => '07:00:00',
                'end_time' => '12:40:00',
                'days_per_week' => 5,
                'display_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ALTERNO',
                'name' => 'Alterno',
                'description' => '3 días a la semana',
                'start_time' => '07:00:00',
                'end_time' => '12:40:00',
                'days_per_week' => 3,
                'display_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'GUARDERIA',
                'name' => 'Guardería',
                'description' => '5 días a la semana',
                'start_time' => '07:00:00',
                'end_time' => '17:00:00',
                'days_per_week' => 5,
                'display_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
