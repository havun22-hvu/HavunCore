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
        Schema::create('havun_restore_logs', function (Blueprint $table) {
            $table->id();

            $table->string('project', 50)->index();
            $table->string('backup_name');
            $table->dateTime('restore_date')->index();
            $table->enum('restore_type', ['production', 'test', 'archive']);
            $table->string('restored_by')->nullable();
            $table->text('restore_reason')->nullable();

            $table->enum('status', ['success', 'failed'])->index();
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('havun_restore_logs');
    }
};
