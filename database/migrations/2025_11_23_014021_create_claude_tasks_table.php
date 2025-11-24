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
        Schema::create('claude_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('project'); // havuncore, havunadmin, herdenkingsportaal
            $table->text('task'); // The instruction/command
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->text('result')->nullable(); // Output/result from Claude
            $table->text('error')->nullable(); // Error message if failed
            $table->string('created_by')->default('api'); // mobile, web, api, cli
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('execution_time_seconds')->nullable();
            $table->json('metadata')->nullable(); // Extra context/data
            $table->timestamps();

            // Indexes for performance
            $table->index(['project', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claude_tasks');
    }
};
