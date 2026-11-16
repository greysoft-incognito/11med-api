<?php

namespace App\Http\Paging;

use Illuminate\Support\Collection;

/**
 * One page of a list: the rows, the cursor for the next page (null at the end) and the total.
 */
final readonly class Page
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
        public int $total,
    ) {}
}
