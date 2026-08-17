<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

/**
 * Shared LIKE escaping for the controllers that build keyword filters, so a
 * literal % or _ typed by the user is matched verbatim instead of behaving as
 * a wildcard. Pair it with an explicit `escape '\'` clause on the query.
 */
trait EscapesLikeWildcards
{
    /**
     * Escape LIKE wildcards so a literal % or _ in the query is matched verbatim.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
