<?php

namespace App\Services\Pharmacy;

use App\Services\Guide\StoreSearch;

/**
 * Finds a listed medicine's name in the guide's text, the longest name first.
 */
class StoreDrugSearch implements StoreSearch
{
    public function __construct(private Storefront $store) {}

    public function drugMentionedIn(string $text): ?string
    {
        $text = mb_strtolower($text);

        $name = $this->store
            ->listedDrugs()
            ->whereRaw('length(drugs.name) >= 3')
            ->whereRaw('position(lower(drugs.name) in ?) > 0', [$text])
            ->orderByRaw('length(drugs.name) desc')
            ->orderBy('drugs.name')
            ->value('drugs.name');

        return $name;
    }
}
