<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_alerts', function (Blueprint $table) {
            $table->id();
            // Dedup key per alert source (e.g. 'reverb', 'JudoToernooi', 'disk').
            // One row per source: down upserts, up resolves.
            $table->string('key', 100)->unique();
            // 'server' = general/server-wide, 'project' = tied to a specific app.
            $table->string('scope', 20)->default('server');
            $table->string('project', 50)->nullable();
            $table->string('severity', 20)->default('warning'); // info|warning|critical
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('status', 20)->default('open'); // open|resolved
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scope']);
            $table->index('project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_alerts');
    }
};
