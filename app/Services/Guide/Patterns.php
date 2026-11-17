<?php

namespace App\Services\Guide;

/**
 * Pattern lists for the rule engine, written without delimiters.
 */
final class Patterns
{
    /**
     * @param  list<string>  $patterns
     */
    public static function any(array $patterns, string $text): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('~' . $pattern . '~u', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lower case, straight apostrophes, single spaces.
     */
    public static function normalise(string $raw): string
    {
        $text = mb_strtolower(str_replace(['’', '‘', '`'], "'", $raw));

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
