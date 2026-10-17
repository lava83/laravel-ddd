<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Pagination;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\Cursor;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;

/**
 * Reads one page of a cursor-paginated list from an API that answers the way a
 * Laravel resource collection does (`data`, `meta.per_page`,
 * `meta.next_cursor`, `meta.prev_cursor`), and builds the request query for it.
 *
 * Failing loudly: an answer that is not such a page, or carries a token that
 * is not a Laravel cursor, must not read as "last page, no results". An API
 * with another shape needs its own small mapper that hands its tokens to
 * RelayedCursorPaginator, which knows no token format.
 */
final readonly class ApiCursorPageMapper
{
    public function __construct(
        private string $perPageParameter = 'per_page',
        private string $cursorParameter = 'cursor',
        private string $filterParameter = 'filter',
    ) {}

    /**
     * Query of a page request; values that are not set are left out.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    public function query(array $filter = [], ?int $perPage = null, ?string $cursor = null): array
    {
        return array_filter([
            $this->perPageParameter => $perPage,
            $this->cursorParameter => $cursor === '' ? null : $cursor,
            $this->filterParameter => $filter === [] ? null : $filter,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @template TReadModel of object
     *
     * @param  string  $path  the path of this application's own endpoint, used for the page links
     * @param  Closure(array<string, mixed>): TReadModel  $mapItem
     * @return RelayedCursorPaginator<TReadModel>
     *
     * @throws ApiException
     */
    public function fromResponse(Response $response, string $path, Closure $mapItem, ?Cursor $cursor = null): RelayedCursorPaginator
    {
        $data = $response->json('data');
        $meta = $response->json('meta');

        if (! is_array($data)) {
            throw $this->invalid('Response has no `data` list', $response);
        }

        if (! is_array($meta)) {
            throw $this->invalid('Response has no `meta` member, it is not a cursor page', $response);
        }

        $perPage = $meta['per_page'] ?? null;

        if (! is_int($perPage)) {
            throw $this->invalid('Response has no integer `meta.per_page`, it is not a cursor page', $response);
        }

        $items = [];

        foreach ($data as $payload) {
            if (! is_array($payload)) {
                throw $this->invalid('Response `data` holds an entry that is not an object', $response);
            }

            /** @var array<string, mixed> $payload */
            $items[] = $mapItem($payload);
        }

        $paginator = new RelayedCursorPaginator(
            $items,
            $perPage,
            $this->token($meta, 'next_cursor', $response),
            $this->token($meta, 'prev_cursor', $response),
            ['path' => $path, 'cursorName' => $this->cursorParameter],
            $cursor,
        );

        return $paginator->withQueryString();
    }

    /**
     * @param  array<mixed>  $meta
     *
     * @throws ApiException
     */
    private function token(array $meta, string $key, Response $response): ?string
    {
        if (! array_key_exists($key, $meta)) {
            throw $this->invalid("Response has no `meta.{$key}`, it is not a cursor page", $response);
        }

        $token = $meta[$key];

        if ($token === null || $token === '') {
            return null;
        }

        if (! is_string($token) || Cursor::fromEncoded($token) === null) {
            throw $this->invalid("`meta.{$key}` is not a cursor token", $response);
        }

        return $token;
    }

    private function invalid(string $message, Response $response): ApiException
    {
        return new ApiException($message, 'GET', $response->effectiveUri()?->getPath() ?? '', $response);
    }
}
