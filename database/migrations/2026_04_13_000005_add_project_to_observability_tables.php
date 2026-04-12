<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_metrics', function (Blueprint $table) {
            $table->string('project', 50)->default('havuncore')->after('id');
            $table->index('project');
        });

        Schema::table('error_logs', function (Blueprint $table) {
            $table->string('project', 50)->default('havuncore')->after('id');
            $table->index('project');
        });

        Schema::table('slow_queries', function (Blueprint $table) {
            $table->string('project', 50)->default('havuncore')->after('id');
            $table->index('project');
        });

        Schema::table('metrics_aggregated', function (Blueprint $table) {
            $table->string('project', 50)->default('havuncore')->after('id');
            // Update unique constraint to include project
            $table->dropUnique(['period', 'period_start', 'path']);
            $table->unique(['project', 'period', 'period_start', 'path']);
        });
    }

    public function down(): void
    {
        Schema::table('request_metrics', function (Blueprint $table) {
            $table->dropIndex(['project']);
            $table->dropColumn('project');
        });

        Schema::table('error_logs', function (Blueprint $table) {
            $table->dropIndex(['project']);
            $table->dropColumn('project');
        });

        Schema::table('slow_queries', function (Blueprint $table) {
            $table->dropIndex(['project']);
            $table->dropColumn('project');
        });

        Schema::table('metrics_aggregated', function (Blueprint $table) {
            $table->dropUnique(['project', 'period', 'period_start', 'path']);
            $table->unique(['period', 'period_start', 'path']);
            $table->dropColumn('project');
        });
    }
};
