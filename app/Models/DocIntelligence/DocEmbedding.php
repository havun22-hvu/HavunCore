<?php

namespace App\Models\DocIntelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocEmbedding extends Model
{
    protected $connection = 'doc_intelligence';
    protected $table = 'doc_embeddings';

    protected $fillable = [
        'project',
        'file_path',
        'content',
        'content_hash',
        'embedding',
        'token_count',
        'file_modified_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'file_modified_at' => 'datetime',
    ];

    /**
     * Get the full local path for this document
     */
    public function getLocalPath(): string
    {
        $basePaths = [
            'havuncore' => 'D:/GitHub/HavunCore',
            'havunadmin' => 'D:/GitHub/HavunAdmin',
            'herdenkingsportaal' => 'D:/GitHub/Herdenkingsportaal',
            'judotoernooi' => 'D:/GitHub/Judotoernooi',
            'infosyst' => 'D:/GitHub/infosyst',
            'studieplanner' => 'D:/GitHub/Studieplanner',
            'safehavun' => 'D:/GitHub/SafeHavun',
            'havun' => 'D:/GitHub/Havun',
            'vpdupdate' => 'D:/GitHub/VPDUpdate',
        ];

        $base = $basePaths[strtolower($this->project)] ?? "D:/GitHub/{$this->project}";
        return $base . '/' . $this->file_path;
    }

    /**
     * Check if the file has changed since last index
     */
    public function hasChanged(): bool
    {
        $path = $this->getLocalPath();
        if (!file_exists($path)) {
            return true;
        }

        $currentHash = hash('sha256', file_get_contents($path));
        return $currentHash !== $this->content_hash;
    }

    /**
     * Calculate cosine similarity with another embedding
     */
    public function cosineSimilarity(array $otherEmbedding): float
    {
        if (empty($this->embedding) || empty($otherEmbedding)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($this->embedding as $i => $val) {
            $otherVal = $otherEmbedding[$i] ?? 0;
            $dotProduct += $val * $otherVal;
            $normA += $val * $val;
            $normB += $otherVal * $otherVal;
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Get issues related to this document
     */
    public function issues(): HasMany
    {
        return $this->hasMany(DocIssue::class, 'project', 'project')
            ->whereJsonContains('affected_files', $this->file_path);
    }

    /**
     * Scope: filter by project
     */
    public function scopeForProject($query, string $project)
    {
        return $query->where('project', strtolower($project));
    }
}
