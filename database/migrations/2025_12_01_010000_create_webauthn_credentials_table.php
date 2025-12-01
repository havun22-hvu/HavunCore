<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('auth_users')->onDelete('cascade');
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->string('name')->default('Passkey');
            $table->unsignedInteger('counter')->default(0);
            $table->json('transports')->nullable();
            $table->string('device_type')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });

        // Store WebAuthn challenges temporarily
        Schema::create('webauthn_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('auth_users')->onDelete('cascade');
            $table->string('challenge', 128)->unique();
            $table->string('type', 20); // 'register' or 'login'
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['challenge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_challenges');
        Schema::dropIfExists('webauthn_credentials');
    }
};
