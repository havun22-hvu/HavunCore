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
        Schema::create('havun_backup_logs', function (Blueprint $table) {
            $table->id();

            // Project info
            $table->string('project', 50)->index();
            $table->string('project_type', 50);

            // Backup info
            $table->string('backup_name')->index();
            $table->dateTime('backup_date')->index();
            $table->unsignedBigInteger('backup_size');
            $table->string('backup_checksum', 64);

            // Storage locations
            $table->boolean('disk_local')->default(true);
            $table->boolean('disk_offsite')->default(true);
            $table->string('offsite_path', 500)->nullable();

            // Status
            $table->enum('status', ['success', 'failed', 'partial'])->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Compliance
            $table->boolean('is_encrypted')->default(false);
            $table->unsignedInteger('retention_years');
            $table->boolean('can_auto_delete')->default(false);

            // Notifications
            $table->boolean('notification_sent')->default(false);
            $table->dateTime('notified_at')->nullable();

            $table->timestamps();

            // Composite indexes
            $table->index(['project', 'backup_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('havun_backup_logs');
    }
};
