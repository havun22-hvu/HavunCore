<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chaos_results', function (Blueprint $table) {
            $table->id();
            $table->string('experiment', 50);
            $table->string('status', 10);
            $table->unsignedInteger('duration_ms');
            $table->json('checks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['experiment', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chaos_results');
    }
};
