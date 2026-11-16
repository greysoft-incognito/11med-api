<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;

final class Transformer
{
    /**
     * Format date
     *
     * @return void
     */
    public static function formatDate(Carbon $date, string $format = 'MMM DD, YYYY')
    {
        return $date->isoFormat($format);
    }

    public static function stringReplace(string $str, string $search, string $replace, bool $caseSensitive = true)
    {
        return str($str)->replace($search, $replace, $caseSensitive);
    }
}
