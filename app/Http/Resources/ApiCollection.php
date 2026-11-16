<?php

namespace App\Http\Resources;

use App\Http\Paging\Page;
use App\Support\Envelope;
use Illuminate\Http\Request;
use Toneflix\ResourceModifier\Services\Json\ResourceCollection;

/**
 * Base for every 11med list. A `Page` renders as `{ items, next_cursor, total }`;
 * a plain collection (a small list read whole) renders as an array.
 */
abstract class ApiCollection extends ResourceCollection
{
    public static $wrap = 'data';

    protected string $message = 'OK';

    protected ?Page $page = null;

    public function __construct(mixed $resource)
    {
        if ($resource instanceof Page) {
            $this->page = $resource;
            $resource = $resource->items;
        }

        parent::__construct($resource);
    }

    public function withMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = parent::toArray($request);

        if ($this->page === null) {
            return $items;
        }

        return [
            'items' => $items,
            'next_cursor' => $this->page->nextCursor,
            'total' => $this->page->total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return Envelope::successKeys($this->message);
    }
}
