<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autofix_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('project', 50);
            $table->string('exception_class', 255);
            $table->text('message');
            $table->string('file', 500)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->text('fix_proposal')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('risk_level', 10)->default('low');
            $table->text('result_message')->nullable();
            $table->string('source', 20)->default('central');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['project', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autofix_proposals');
    }
};
