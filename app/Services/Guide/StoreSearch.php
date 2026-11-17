<?php

namespace App\Services\Guide;

use Illuminate\Container\Attributes\Bind;

/**
 * The store's drug names, for recognising "buy X". The Pharmacy module (M11) implements it.
 */
#[Bind(NoStoreSearch::class)]
interface StoreSearch
{
    /**
     * The name of a drug the store lists that appears in the text, or null.
     */
    public function drugMentionedIn(string $text): ?string;
}
