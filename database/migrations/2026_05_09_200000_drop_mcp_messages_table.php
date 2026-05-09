<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('mcp_messages');
    }

    public function down(): void
    {
        // No rollback — MCP layer is permanently removed (ADR 007).
        // The original create migration stays in history for traceability.
    }
};
