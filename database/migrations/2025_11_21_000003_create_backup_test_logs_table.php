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
        Schema::create('havun_backup_test_logs', function (Blueprint $table) {
            $table->id();

            $table->string('project', 50)->index();
            $table->string('test_quarter', 10)->index(); // 2025-Q4
            $table->dateTime('test_date');
            $table->string('backup_tested');

            $table->enum('test_result', ['pass', 'fail'])->index();
            $table->text('test_report')->nullable();

            $table->json('checked_items')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('havun_backup_test_logs');
    }
};
