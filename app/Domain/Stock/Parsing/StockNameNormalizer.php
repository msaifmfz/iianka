<?php

declare(strict_types=1);

namespace App\Domain\Stock\Parsing;

use Normalizer;

/**
 * Canonical normalization for stock names, aliases, and schedule content
 * matching. The exact same rules are mirrored client-side in
 * resources/js/components/schedule-content-editor/catalog.ts — keep both in
 * sync when changing anything here.
 */
class StockNameNormalizer
{
    public function normalize(string $value): string
    {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);

        if ($normalized === false) {
            $normalized = $value;
        }

        $normalized = mb_strtolower($normalized);

        $normalized = preg_replace('/[\x{00A0}\x{3000}\s]+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
