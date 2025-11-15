<?php

namespace Havun\Core\Services;

/**
 * Memorial Reference Service
 *
 * Handles memorial reference extraction and validation
 * Memorial reference = first 12 characters of monument UUID
 * Used for linking transactions across Herdenkingsportaal, Mollie, Bunq, Gmail
 */
class MemorialReferenceService
{
    /**
     * Extract memorial reference from text
     * Searches for 12-character alphanumeric string (UUID prefix)
     *
     * @param string $text
     * @return string|null
     */
    public function extractMemorialReference(string $text): ?string
    {
        // Pattern: 12 alphanumeric characters (UUID format without hyphens)
        // Example: 550e8400e29b (from 550e8400-e29b-41d4-a716-446655440000)

        if (preg_match('/\b([a-f0-9]{12})\b/i', $text, $matches)) {
            return strtolower($matches[1]);
        }

        // Alternative: UUID with hyphens, extract first 12 chars
        if (preg_match('/\b([a-f0-9]{8}-[a-f0-9]{4})\b/i', $text, $matches)) {
            return str_replace('-', '', strtolower($matches[1]));
        }

        return null;
    }

    /**
     * Validate memorial reference format
     *
     * @param string $reference
     * @return bool
     */
    public function isValidReference(string $reference): bool
    {
        return (bool) preg_match('/^[a-f0-9]{12}$/i', $reference);
    }

    /**
     * Generate memorial reference from full UUID
     *
     * @param string $uuid
     * @return string
     */
    public function fromUuid(string $uuid): string
    {
        $cleaned = str_replace('-', '', strtolower($uuid));
        return substr($cleaned, 0, 12);
    }

    /**
     * Format reference for display
     * Example: 550e8400e29b → 550e-8400-e29b
     *
     * @param string $reference
     * @return string
     */
    public function formatReference(string $reference): string
    {
        if (!$this->isValidReference($reference)) {
            return $reference;
        }

        return substr($reference, 0, 4) . '-' .
               substr($reference, 4, 4) . '-' .
               substr($reference, 8, 4);
    }
}
