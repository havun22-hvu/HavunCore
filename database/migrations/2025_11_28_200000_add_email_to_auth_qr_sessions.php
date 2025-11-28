<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_qr_sessions', function (Blueprint $table) {
            $table->string('email')->nullable()->after('ip_address');
            $table->string('email_token', 64)->nullable()->after('email');
            $table->string('callback_url')->nullable()->after('email_token');
            $table->timestamp('email_sent_at')->nullable()->after('callback_url');

            $table->index('email_token');
        });
    }

    public function down(): void
    {
        Schema::table('auth_qr_sessions', function (Blueprint $table) {
            $table->dropIndex(['email_token']);
            $table->dropColumn(['email', 'email_token', 'callback_url', 'email_sent_at']);
        });
    }
};
