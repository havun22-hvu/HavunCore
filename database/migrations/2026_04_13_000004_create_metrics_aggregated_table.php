<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics_aggregated', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10);
            $table->timestamp('period_start');
            $table->string('path', 500)->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('server_error_count')->default(0);
            $table->decimal('avg_response_time_ms', 10, 2)->default(0);
            $table->decimal('p95_response_time_ms', 10, 2)->default(0);
            $table->decimal('p99_response_time_ms', 10, 2)->default(0);
            $table->decimal('min_response_time_ms', 10, 2)->default(0);
            $table->decimal('max_response_time_ms', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['period', 'period_start', 'path']);
            $table->index(['period', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics_aggregated');
    }
};
