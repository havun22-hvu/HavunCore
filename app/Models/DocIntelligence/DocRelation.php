<?php

namespace App\Models\DocIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocRelation extends Model
{
    protected $connection = 'doc_intelligence';
    protected $table = 'doc_relations';

    protected $fillable = [
        'source_project',
        'source_file',
        'target_project',
        'target_file',
        'relation_type',
        'confidence',
        'auto_detected',
        'details',
    ];

    protected $casts = [
        'confidence' => 'float',
        'auto_detected' => 'boolean',
    ];

    // Relation types
    const TYPE_REFERENCES = 'references';      // Source mentions/links to target
    const TYPE_DUPLICATES = 'duplicates';      // Same content in both files
    const TYPE_CONTRADICTS = 'contradicts';    // Conflicting information
    const TYPE_EXTENDS = 'extends';            // Source builds upon target

    /**
     * Get human-readable relation type
     */
    public function getTypeLabel(): string
    {
        return match($this->relation_type) {
            self::TYPE_REFERENCES => '🔗 References',
            self::TYPE_DUPLICATES => '📋 Duplicates',
            self::TYPE_CONTRADICTS => '⚠️ Contradicts',
            self::TYPE_EXTENDS => '📝 Extends',
            default => $this->relation_type,
        };
    }

    /**
     * Get the source document
     */
    public function sourceDocument(): ?DocEmbedding
    {
        return DocEmbedding::where('project', $this->source_project)
            ->where('file_path', $this->source_file)
            ->first();
    }

    /**
     * Get the target document
     */
    public function targetDocument(): ?DocEmbedding
    {
        return DocEmbedding::where('project', $this->target_project)
            ->where('file_path', $this->target_file)
            ->first();
    }

    /**
     * Scope: filter by relation type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('relation_type', $type);
    }

    /**
     * Scope: high confidence relations
     */
    public function scopeHighConfidence($query, float $threshold = 0.8)
    {
        return $query->where('confidence', '>=', $threshold);
    }

    /**
     * Scope: problematic relations (duplicates or contradicts)
     */
    public function scopeProblematic($query)
    {
        return $query->whereIn('relation_type', [
            self::TYPE_DUPLICATES,
            self::TYPE_CONTRADICTS,
        ]);
    }
}
