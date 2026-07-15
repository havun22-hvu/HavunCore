<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunks live in their own table rather than as extra doc_embeddings rows on
 * purpose: roughly thirty callers assume one row is one file — IssueDetector
 * parses `content` as a whole markdown document, and the API reports COUNT(*)
 * as `total_files`. Widening that table's unique key would have broken all of
 * them. Here, only search() has to look somewhere else.
 *
 * See docs/kb/plans/kb-chunking-plan.md.
 */
return new class extends Migration
{
    protected $connection = 'doc_intelligence';

    public function up(): void
    {
        Schema::connection('doc_intelligence')->create('doc_chunks', function (Blueprint $table) {
            $table->id();
            // Cascade so a deleted file takes its chunks with it: cleanupOrphaned()
            // deletes doc_embeddings rows and must not leave chunks behind to be
            // matched against. The connection has foreign_key_constraints enabled.
            $table->foreignId('doc_embedding_id')
                ->constrained('doc_embeddings')
                ->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');       // 0-based order within the file
            $table->string('heading', 500)->nullable();   // heading path, for the search snippet
            $table->text('content');                      // this chunk only, not the whole file
            $table->json('embedding')->nullable();
            $table->string('embedding_model', 100)->nullable();
            $table->integer('token_count')->default(0);
            $table->timestamps();

            $table->unique(['doc_embedding_id', 'chunk_index']);
            $table->index('doc_embedding_id');
        });
    }

    public function down(): void
    {
        Schema::connection('doc_intelligence')->dropIfExists('doc_chunks');
    }
};
