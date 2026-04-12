<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slow_queries', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->decimal('time_ms', 10, 2);
            $table->string('connection', 50)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('time_ms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slow_queries');
    }
};
