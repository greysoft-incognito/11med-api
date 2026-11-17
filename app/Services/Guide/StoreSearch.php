<?php

namespace App\Services\Guide;

use App\Services\Pharmacy\StoreDrugSearch;
use Illuminate\Container\Attributes\Bind;

/**
 * The store's drug names, for recognising "buy X". The Pharmacy module implements it.
 */
#[Bind(StoreDrugSearch::class)]
interface StoreSearch
{
    /**
     * The name of a drug the store lists that appears in the text, or null.
     */
    public function drugMentionedIn(string $text): ?string;
}
