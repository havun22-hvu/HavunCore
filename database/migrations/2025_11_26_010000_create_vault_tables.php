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
        // Vault secrets - encrypted key-value store
        Schema::create('vault_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value'); // encrypted
            $table->string('category')->nullable(); // e.g., 'payment', 'storage', 'api'
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(true);
            $table->timestamps();

            $table->index('category');
        });

        // Vault configs - project templates and shared configurations
        Schema::create('vault_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type'); // 'laravel', 'nodejs', 'shared'
            $table->json('config'); // JSON structure with config values
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('type');
        });

        // Vault projects - which project uses which secrets/configs
        Schema::create('vault_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project')->unique(); // e.g., 'havunadmin', 'herdenkingsportaal'
            $table->json('secrets'); // array of secret keys this project uses
            $table->json('configs'); // array of config names this project uses
            $table->string('api_token')->unique(); // for API authentication
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
        });

        // Vault access log - audit trail
        Schema::create('vault_access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->string('action'); // 'read', 'write', 'delete'
            $table->string('resource_type'); // 'secret', 'config'
            $table->string('resource_key');
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at');

            $table->index(['project', 'created_at']);
            $table->index('resource_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_access_logs');
        Schema::dropIfExists('vault_projects');
        Schema::dropIfExists('vault_configs');
        Schema::dropIfExists('vault_secrets');
    }
};
