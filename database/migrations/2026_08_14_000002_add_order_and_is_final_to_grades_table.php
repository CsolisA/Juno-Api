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
     * `order` drives grade progression for pre-enrollment campaigns; `is_final` is deliberately
     * explicit rather than derived from MAX(order), so adding a level above the current final
     * grade later can't silently change who graduates.
     */
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->unsignedTinyInteger('order')->nullable()->after('name');
            $table->boolean('is_final')->default(false)->after('order');
        });

        // Backfill existing rows by id-ascending rank, which matches the pedagogical
        // sequence they were seeded in (Maternal, Prekinder, Kinder, Preparatoria).
        DB::statement('
            UPDATE grades g
            JOIN (SELECT id, ROW_NUMBER() OVER (ORDER BY id) - 1 AS rn FROM grades) ranked
              ON ranked.id = g.id
            SET g.`order` = ranked.rn
        ');

        DB::statement('
            UPDATE grades
            SET is_final = true
            WHERE `order` = (SELECT max_order FROM (SELECT MAX(`order`) AS max_order FROM grades) x)
        ');

        Schema::table('grades', function (Blueprint $table) {
            $table->unsignedTinyInteger('order')->nullable(false)->change();
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->unique('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropUnique(['order']);
            $table->dropColumn(['order', 'is_final']);
        });
    }
};
