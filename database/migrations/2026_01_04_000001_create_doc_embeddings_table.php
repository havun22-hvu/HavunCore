<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'doc_intelligence';

    public function up(): void
    {
        Schema::connection('doc_intelligence')->create('doc_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('project', 50);           // 'herdenkingsportaal', 'havunadmin', etc.
            $table->string('file_path', 500);        // 'docs/SPEC.md', '.claude/context.md'
            $table->text('content');                  // Full content of the file
            $table->string('content_hash', 64);       // SHA256 hash for change detection
            $table->json('embedding')->nullable();    // Vector embedding as JSON array
            $table->integer('token_count')->default(0); // Number of tokens in content
            $table->timestamp('file_modified_at')->nullable(); // File modification time
            $table->timestamps();

            $table->unique(['project', 'file_path']);
            $table->index('project');
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('doc_intelligence')->dropIfExists('doc_embeddings');
    }
};
