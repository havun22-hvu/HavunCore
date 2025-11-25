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
        Schema::create('mcp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('project');
            $table->text('content');
            $table->json('tags')->nullable();
            $table->string('external_id')->nullable()->unique();
            $table->timestamps();

            $table->index('project');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_messages');
    }
};
