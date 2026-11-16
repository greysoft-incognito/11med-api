<?php

namespace App\Http\Paging;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

/**
 * The clients' paging: `cursor` and `limit` up, `{ items, next_cursor, total }` back.
 * The cursor is a keyset over the query's order, opaque to the client.
 */
final readonly class ListQuery
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    public function __construct(
        public int $limit,
        public ?Cursor $cursor,
    ) {}

    public static function from(Request $request, int $defaultLimit = self::DEFAULT_LIMIT): self
    {
        $limit = $request->integer('limit', $defaultLimit);
        $limit = $limit < 1 ? $defaultLimit : min($limit, self::MAX_LIMIT);

        $encoded = $request->string('cursor')->toString();
        $cursor = Cursor::fromEncoded($encoded);

        if ($encoded !== '' && $cursor === null) {
            throw ApiException::badRequest('VALIDATION_FAILED', 'cursor', __('errors.INVALID_CURSOR'));
        }

        return new self($limit, $cursor);
    }

    /**
     * Page a query. Its order must end on a unique column so the keyset never repeats a row.
     */
    public function paginate(Builder $query): Page
    {
        $total = $query
            ->clone()
            ->reorder()
            ->count();

        $page = $query->cursorPaginate($this->limit, ['*'], 'cursor', $this->cursor);

        return new Page(collect($page->items()), $page->nextCursor()?->encode(), $total);
    }
}
