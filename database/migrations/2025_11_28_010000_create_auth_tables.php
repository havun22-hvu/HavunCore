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
        // Auth users - centralized user management
        Schema::create('auth_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('password_hash')->nullable(); // Fallback login
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });

        // Auth devices - trusted devices for passwordless login
        Schema::create('auth_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->cascadeOnDelete();
            $table->string('device_name'); // "Chrome Windows", "Safari iPhone"
            $table->string('device_hash', 64); // Fingerprint hash
            $table->string('token', 64)->unique(); // Device token
            $table->timestamp('expires_at'); // +30 days
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('token');
            $table->index('device_hash');
        });

        // Auth QR sessions - for QR code login flow
        Schema::create('auth_qr_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('qr_code', 64)->unique(); // Random token in QR
            $table->json('device_info')->nullable(); // Browser/OS info
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['pending', 'scanned', 'approved', 'expired'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('auth_devices')->nullOnDelete();
            $table->timestamp('expires_at'); // +5 minutes
            $table->timestamps();

            $table->index('qr_code');
            $table->index(['status', 'expires_at']);
        });

        // Auth access logs - audit trail
        Schema::create('auth_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('action'); // 'login', 'logout', 'qr_scan', 'device_revoke'
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_access_logs');
        Schema::dropIfExists('auth_qr_sessions');
        Schema::dropIfExists('auth_devices');
        Schema::dropIfExists('auth_users');
    }
};
