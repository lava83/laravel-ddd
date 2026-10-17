<?php

declare(strict_types=1);

use Illuminate\Pagination\Cursor;
use Lava83\LaravelDdd\Infrastructure\Pagination\RelayedCursor;
use Lava83\LaravelDdd\Infrastructure\Pagination\RelayedCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Application\Resources\CursorTestResource;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels\CursorTestReadModel;

function relayedToken(int $id, bool $pointsToNext = true): string
{
    return (new Cursor(['account_id' => $id], $pointsToNext))->encode();
}

/**
 * @param  list<int>  $ids
 */
function relayedPage(array $ids, ?string $next, ?string $prev, array $options = []): RelayedCursorPaginator
{
    return new RelayedCursorPaginator(
        array_map(fn (int $id) => new CursorTestReadModel($id, 'owner'), $ids),
        2,
        $next,
        $prev,
        ['path' => '/accounts', ...$options],
    );
}

describe('RelayedCursorPaginator', function (): void {
    it('relays the tokens it was given, first page without a previous cursor', function (): void {
        $page = relayedPage([1, 2], relayedToken(2), null);

        expect($page->nextCursor()?->encode())->toBe(relayedToken(2))
            ->and($page->previousCursor())->toBeNull()
            ->and($page->onFirstPage())->toBeTrue()
            ->and($page->hasMorePages())->toBeTrue();
    });

    it('relays both tokens on a middle page', function (): void {
        $page = relayedPage([3, 4], relayedToken(4), relayedToken(3, false));

        expect($page->nextCursor()?->pointsToNextItems())->toBeTrue()
            ->and($page->previousCursor()?->pointsToPreviousItems())->toBeTrue()
            ->and($page->previousCursor()?->encode())->toBe(relayedToken(3, false))
            ->and($page->onFirstPage())->toBeFalse()
            ->and($page->hasMorePages())->toBeTrue();
    });

    it('has no next cursor on the last page', function (): void {
        $page = relayedPage([5], null, relayedToken(5, false));

        expect($page->nextCursor())->toBeNull()
            ->and($page->hasMorePages())->toBeFalse()
            ->and($page->onLastPage())->toBeTrue()
            ->and($page->onFirstPage())->toBeFalse();
    });

    it('hands a token on byte for byte, although decoding and re-encoding would change it', function (): void {
        $token = rtrim(strtr(base64_encode('{"account_id": 7, "_pointsToNextItems": true}'), '+/', '-_'), '=');

        $page = relayedPage([1], $token, null);

        expect(Cursor::fromEncoded($token)?->encode())->not->toBe($token)
            ->and($page->nextCursor()?->encode())->toBe($token);
    });

    it('takes nothing from the items, so they need no sort column', function (): void {
        $page = new RelayedCursorPaginator(
            [new stdClass, new stdClass],
            2,
            relayedToken(2),
            null,
            ['path' => '/accounts'],
        );

        expect($page->nextCursor()?->encode())->toBe(relayedToken(2))
            ->and($page->items())->toHaveCount(2);
    });

    it('keeps the items as given: not trimmed to the page size and not reversed', function (): void {
        $page = new RelayedCursorPaginator(
            [new CursorTestReadModel(3, 'a'), new CursorTestReadModel(2, 'b'), new CursorTestReadModel(1, 'c')],
            2,
            relayedToken(3),
            null,
            ['path' => '/accounts'],
            new Cursor(['account_id' => 4], false),
        );

        expect(array_map(fn (CursorTestReadModel $r) => $r->id, $page->items()))->toBe([3, 2, 1]);
    });

    it('is a valid empty page', function (): void {
        $page = relayedPage([], null, null);

        expect($page->items())->toBe([])
            ->and($page->nextCursor())->toBeNull()
            ->and($page->previousCursor())->toBeNull()
            ->and($page->hasMorePages())->toBeFalse()
            ->and($page->onFirstPage())->toBeTrue();
    });

    it('serialises through a resource collection with data, links and meta', function (): void {
        $page = relayedPage([1, 2], relayedToken(2), null);

        $body = CursorTestResource::collection($page)->toResponse(request())->getData(true);

        expect($body['data'])->toBe([['id' => 1, 'owner' => 'owner'], ['id' => 2, 'owner' => 'owner']])
            ->and($body['links'])->toBe([
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => '/accounts?cursor='.relayedToken(2),
            ])
            ->and($body['meta'])->toMatchArray([
                'path' => '/accounts',
                'per_page' => 2,
                'next_cursor' => relayedToken(2),
                'prev_cursor' => null,
            ]);
    });

    it('writes both cursors into meta and links on a middle page', function (): void {
        $page = relayedPage([3, 4], relayedToken(4), relayedToken(3, false));

        $body = CursorTestResource::collection($page)->toResponse(request())->getData(true);

        expect($body['meta']['next_cursor'])->toBe(relayedToken(4))
            ->and($body['meta']['prev_cursor'])->toBe(relayedToken(3, false))
            ->and($body['links']['next'])->toBe('/accounts?cursor='.relayedToken(4))
            ->and($body['links']['prev'])->toBe('/accounts?cursor='.relayedToken(3, false));
    });

    it('keeps query parameters in the links and names the cursor as configured', function (): void {
        $page = relayedPage([1, 2], relayedToken(2), null, ['query' => ['filter' => 'active'], 'cursorName' => 'after']);

        expect($page->nextPageUrl())->toBe('/accounts?filter=active&after='.relayedToken(2));
    });

    it('does not decode a relayed cursor', function (): void {
        expect(fn () => RelayedCursor::fromEncoded(relayedToken(1)))->toThrow(BadMethodCallException::class);
    });
});
