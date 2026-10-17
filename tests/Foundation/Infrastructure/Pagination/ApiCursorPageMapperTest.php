<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Lava83\LaravelDdd\Infrastructure\Api\Exceptions\ApiException;
use Lava83\LaravelDdd\Infrastructure\Pagination\ApiCursorPageMapper;
use Lava83\LaravelDdd\Infrastructure\Pagination\ReadModelCursorPaginator;
use Lava83\LaravelDdd\Tests\Fixtures\Application\Resources\CursorTestResource;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels\CursorTestReadModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\CursorTestModel;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Models\CursorTestOwnerModel;

/**
 * @param  array<string, mixed>  $body
 */
function pageResponse(array $body): Response
{
    Http::fake(['*' => Http::response($body)]);

    return Http::get('https://api.test/v1/accounts');
}

function mapAccount(): Closure
{
    /** @param array{id: int, owner: string} $payload */
    return fn (array $payload) => new CursorTestReadModel($payload['id'], $payload['owner']);
}

function pageToken(int $id, bool $pointsToNext = true): string
{
    return (new Cursor(['account_id' => $id], $pointsToNext))->encode();
}

describe('ApiCursorPageMapper', function (): void {
    describe('query', function (): void {
        it('leaves out what is not set', function (): void {
            expect((new ApiCursorPageMapper)->query())->toBe([]);
        });

        it('builds the query with the default parameter names', function (): void {
            $query = (new ApiCursorPageMapper)->query(['status' => ['active', 'new'], 'name' => 'x'], 25, 'abc');

            expect($query)->toBe([
                'per_page' => 25,
                'cursor' => 'abc',
                'filter' => ['status' => ['active', 'new'], 'name' => 'x'],
            ])->and(http_build_query($query))->toContain('filter%5Bstatus%5D%5B0%5D=active');
        });

        it('treats an empty cursor and an empty filter as not set', function (): void {
            expect((new ApiCursorPageMapper)->query([], 10, ''))->toBe(['per_page' => 10]);
        });

        it('uses configured parameter names', function (): void {
            $mapper = new ApiCursorPageMapper(perPageParameter: 'limit', cursorParameter: 'after', filterParameter: 'where');

            expect($mapper->query(['a' => 1], 5, 'tok'))->toBe(['limit' => 5, 'after' => 'tok', 'where' => ['a' => 1]]);
        });
    });

    describe('fromResponse', function (): void {
        it('reads items and cursors of a middle page', function (): void {
            $response = pageResponse([
                'data' => [['id' => 3, 'owner' => 'c'], ['id' => 4, 'owner' => 'd']],
                'meta' => ['per_page' => 2, 'next_cursor' => pageToken(4), 'prev_cursor' => pageToken(3, false)],
            ]);

            $page = (new ApiCursorPageMapper)->fromResponse($response, '/accounts', mapAccount());

            expect($page->items())->toHaveCount(2)
                ->and($page->items()[0])->toBeInstanceOf(CursorTestReadModel::class)
                ->and($page->perPage())->toBe(2)
                ->and($page->nextCursor()?->encode())->toBe(pageToken(4))
                ->and($page->previousCursor()?->encode())->toBe(pageToken(3, false));
        });

        it('reads empty cursor strings and null as no cursor', function (): void {
            $response = pageResponse([
                'data' => [],
                'meta' => ['per_page' => 2, 'next_cursor' => '', 'prev_cursor' => null],
            ]);

            $page = (new ApiCursorPageMapper)->fromResponse($response, '/accounts', mapAccount());

            expect($page->items())->toBe([])
                ->and($page->nextCursor())->toBeNull()
                ->and($page->previousCursor())->toBeNull();
        });

        it('links to its own path, not the one of the API', function (): void {
            $response = pageResponse([
                'data' => [['id' => 1, 'owner' => 'a']],
                'meta' => ['path' => 'https://api.test/v1/accounts', 'per_page' => 1, 'next_cursor' => pageToken(1), 'prev_cursor' => null],
            ]);

            $page = (new ApiCursorPageMapper)->fromResponse($response, '/accounts', mapAccount());

            expect($page->nextPageUrl())->toBe('/accounts?cursor='.pageToken(1));
        });

        it('keeps the request query in the links, without the cursor itself', function (): void {
            $this->app->instance('request', Request::create('/accounts', 'GET', ['filter' => 'active', 'cursor' => 'old']));

            $response = pageResponse([
                'data' => [['id' => 1, 'owner' => 'a']],
                'meta' => ['per_page' => 1, 'next_cursor' => pageToken(1), 'prev_cursor' => null],
            ]);

            $page = (new ApiCursorPageMapper)->fromResponse($response, '/accounts', mapAccount());

            expect($page->nextPageUrl())->toBe('/accounts?filter=active&cursor='.pageToken(1));
        });

        it('names the cursor in the links as configured', function (): void {
            $response = pageResponse([
                'data' => [['id' => 1, 'owner' => 'a']],
                'meta' => ['per_page' => 1, 'next_cursor' => pageToken(1), 'prev_cursor' => null],
            ]);

            $page = (new ApiCursorPageMapper(cursorParameter: 'after'))->fromResponse($response, '/accounts', mapAccount());

            expect($page->nextPageUrl())->toBe('/accounts?after='.pageToken(1));
        });

        it('fails loudly instead of reading a broken answer as the last page', function (array $body, string $message): void {
            $response = pageResponse($body);

            expect(fn () => (new ApiCursorPageMapper)->fromResponse($response, '/accounts', mapAccount()))
                ->toThrow(ApiException::class, $message);
        })->with([
            'no data' => [['meta' => ['per_page' => 2, 'next_cursor' => null, 'prev_cursor' => null]], '`data`'],
            'data is not a list' => [['data' => 'x', 'meta' => ['per_page' => 2, 'next_cursor' => null, 'prev_cursor' => null]], '`data`'],
            'no meta (unpaginated list)' => [['data' => [['id' => 1, 'owner' => 'a']]], '`meta`'],
            'no per_page' => [['data' => [], 'meta' => ['next_cursor' => null, 'prev_cursor' => null]], '`meta.per_page`'],
            'per_page is not an integer' => [['data' => [], 'meta' => ['per_page' => '2', 'next_cursor' => null, 'prev_cursor' => null]], '`meta.per_page`'],
            'no next_cursor key' => [['data' => [], 'meta' => ['per_page' => 2, 'prev_cursor' => null]], '`meta.next_cursor`'],
            'no prev_cursor key' => [['data' => [], 'meta' => ['per_page' => 2, 'next_cursor' => null]], '`meta.prev_cursor`'],
            'next_cursor is not a token' => [['data' => [], 'meta' => ['per_page' => 2, 'next_cursor' => 'garbage', 'prev_cursor' => null]], '`meta.next_cursor` is not a cursor token'],
            'prev_cursor is not a string' => [['data' => [], 'meta' => ['per_page' => 2, 'next_cursor' => null, 'prev_cursor' => 5]], '`meta.prev_cursor` is not a cursor token'],
            'data entry is not an object' => [['data' => ['x'], 'meta' => ['per_page' => 2, 'next_cursor' => null, 'prev_cursor' => null]], 'not an object'],
        ]);

        it('carries the response in the exception', function (): void {
            $response = pageResponse(['data' => []]);

            try {
                (new ApiCursorPageMapper)->fromResponse($response, '/accounts', mapAccount());
            } catch (ApiException $exception) {
                expect($exception->response)->toBe($response)
                    ->and($exception->method)->toBe('GET');

                return;
            }

            $this->fail('ApiException expected');
        });
    });

    describe('round trip with ReadModelCursorPaginator', function (): void {
        beforeEach(function (): void {
            Schema::dropIfExists('cursor_test_items');
            Schema::dropIfExists('cursor_test_owners');

            Schema::create('cursor_test_owners', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
            });

            Schema::create('cursor_test_items', function (Blueprint $table): void {
                $table->id('account_id');
                $table->unsignedBigInteger('owner_id');
            });

            CursorTestOwnerModel::query()->create(['name' => 'owner']);

            for ($i = 0; $i < 7; $i++) {
                CursorTestModel::query()->create(['owner_id' => 1]);
            }
        });

        it('reads a page the producing side serialised and gives back the same meta and links', function (): void {
            $first = CursorTestModel::query()->orderBy('account_id')->cursorPaginate(2);

            // Second page: both cursors are set.
            $page = CursorTestModel::query()->with('owner')->orderBy('account_id')->cursorPaginate(2, cursor: $first->nextCursor());

            $server = ReadModelCursorPaginator::fromPage(
                $page,
                fn (CursorTestModel $m) => new CursorTestReadModel($m->account_id, $m->owner->name),
            );

            $serverBody = CursorTestResource::collection($server)->toResponse(request())->getData(true);

            $relayed = (new ApiCursorPageMapper)->fromResponse(
                pageResponse($serverBody),
                $serverBody['meta']['path'],
                mapAccount(),
            );

            $relayedBody = CursorTestResource::collection($relayed)->toResponse(request())->getData(true);

            expect($serverBody['meta']['next_cursor'])->toBeString()
                ->and($serverBody['meta']['prev_cursor'])->toBeString()
                ->and($relayedBody['meta'])->toBe($serverBody['meta'])
                ->and($relayedBody['links'])->toBe($serverBody['links'])
                ->and($relayedBody['data'])->toBe($serverBody['data']);
        });
    });
});
