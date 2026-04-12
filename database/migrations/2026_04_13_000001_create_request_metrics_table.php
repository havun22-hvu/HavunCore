<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 500);
            $table->string('route_name', 100)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms');
            $table->string('ip_address', 45)->nullable();
            $table->string('tenant', 50)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedInteger('memory_usage_kb')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['path', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index('tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_metrics');
    }
};
