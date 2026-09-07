<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `password` becomes nullable because an invited staff member has no password until they
     * accept the invite and set one themselves.
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->enum('credential_method', ['invite', 'temp_password'])->nullable()->after('password');
            $table->boolean('must_reset_password')->default(false)->after('credential_method');
            $table->string('invite_token')->nullable()->unique()->after('must_reset_password');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['credential_method', 'must_reset_password', 'invite_token', 'invite_expires_at']);
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
