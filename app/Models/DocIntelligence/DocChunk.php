<?php

namespace App\Models\DocIntelligence;

use App\Casts\Float32Vector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One embedded slice of a document.
 *
 * Documents longer than Ollama's ~2048-token context used to be embedded on
 * their opening section alone, leaving the rest unsearchable — 22-59% of the
 * KB, depending on where the ceiling fell per file. Chunks carry the vectors
 * now; doc_embeddings keeps the whole file for everything else.
 */
class DocChunk extends Model
{
    protected $connection = 'doc_intelligence';
    protected $table = 'doc_chunks';

    protected $fillable = [
        'doc_embedding_id',
        'chunk_index',
        'heading',
        'content',
        'embedding',
        'embedding_model',
        'token_count',
    ];

    protected $casts = [
        'embedding' => Float32Vector::class,
        'chunk_index' => 'integer',
        'token_count' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocEmbedding::class, 'doc_embedding_id');
    }
}
