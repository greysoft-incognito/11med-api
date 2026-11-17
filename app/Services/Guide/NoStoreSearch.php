<?php

namespace App\Services\Guide;

/**
 * No drugs are listed until the Pharmacy module is.
 */
class NoStoreSearch implements StoreSearch
{
    public function drugMentionedIn(string $text): ?string
    {
        return null;
    }
}
