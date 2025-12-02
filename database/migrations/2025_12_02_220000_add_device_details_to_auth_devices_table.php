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
        Schema::table('auth_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('auth_devices', 'browser')) {
                $table->string('browser', 100)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('auth_devices', 'os')) {
                $table->string('os', 100)->nullable()->after('browser');
            }
            if (!Schema::hasColumn('auth_devices', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('os');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth_devices', function (Blueprint $table) {
            $table->dropColumn(['browser', 'os', 'user_agent']);
        });
    }
};
