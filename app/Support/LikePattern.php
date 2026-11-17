<?php

namespace App\Support;

/**
 * A free-text search term as a `LIKE` pattern matching it anywhere, its wildcards escaped.
 */
final class LikePattern
{
    public static function contains(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
