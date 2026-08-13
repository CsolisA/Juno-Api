<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The family-portal "add authorized contact" payload (directions.md #6) doesn't collect
     * province/canton/address, so these can no longer be required at creation time.
     */
    public function up(): void
    {
        Schema::table('authorized', function (Blueprint $table) {
            $table->dropColumn(['province', 'canton', 'address']);
        });

        Schema::table('authorized', function (Blueprint $table) {
            $table->string('province')->nullable()->after('lives_with_child');
            $table->string('canton')->nullable()->after('province');
            $table->string('address')->nullable()->after('canton');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authorized', function (Blueprint $table) {
            $table->dropColumn(['province', 'canton', 'address']);
        });

        Schema::table('authorized', function (Blueprint $table) {
            $table->string('province')->after('lives_with_child');
            $table->string('canton')->after('province');
            $table->string('address')->after('canton');
        });
    }
};
