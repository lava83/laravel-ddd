<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FullListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\SearchedListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\TunedListRequest;

describe('AcceptsSearch', function (): void {
    it('adds the phrase as the search filter', function (): void {
        expect(ListRequests::resolve(SearchedListRequest::class, ['search' => 'anna mü'])->filters()->toArray())
            ->toBe([['type' => '$listTestSearch', 'value' => 'anna mü']]);
    });

    it('trims the phrase', function (): void {
        $request = ListRequests::resolve(SearchedListRequest::class, ['search' => "  anna \n"]);

        expect($request->searchPhrase())->toBe('anna')
            ->and($request->filters()->toArray())->toBe([['type' => '$listTestSearch', 'value' => 'anna']]);
    });

    it('adds no filter for a missing or blank phrase', function (array $query): void {
        $request = ListRequests::resolve(SearchedListRequest::class, $query);

        expect($request->searchPhrase())->toBeNull()
            ->and($request->filters()->toArray())->toBe([]);
    })->with([
        'missing' => [[]],
        'empty' => [['search' => '']],
        'blank' => [['search' => '   ']],
    ]);

    it('rejects a phrase longer than 100 characters', function (): void {
        expect(ListRequests::errors(SearchedListRequest::class, ['search' => str_repeat('a', 100)]))->toBe([])
            ->and(ListRequests::errors(SearchedListRequest::class, ['search' => str_repeat('a', 101)]))->toHaveKey('search');
    });

    it('takes the maximum length of the request', function (): void {
        expect(ListRequests::errors(TunedListRequest::class, ['search' => str_repeat('a', 10)]))->toBe([])
            ->and(ListRequests::errors(TunedListRequest::class, ['search' => str_repeat('a', 11)]))->toHaveKey('search');
    });

    it('rejects a phrase that is not a string', function (): void {
        expect(ListRequests::errors(SearchedListRequest::class, ['search' => ['anna']]))->toHaveKey('search');
    });

    it('appends the phrase to the client filters', function (): void {
        $request = ListRequests::resolve(FullListRequest::class, [
            'filters' => '[{"type":"$eq","target":"status_id","value":2}]',
            'search' => 'anna',
        ]);

        expect($request->filters()->toArray())->toBe([
            ['type' => '$eq', 'target' => 'status_id', 'value' => 2],
            ['type' => '$listTestSearch', 'value' => 'anna'],
        ]);
    });

    it('refuses a search type a client may send through filters', function (): void {
        config()->set('laravel-ddd.filters.custom_types', ['$listTestSearch']);

        $request = ListRequests::resolve(SearchedListRequest::class, ['search' => 'anna']);

        expect(fn () => $request->filters())->toThrow(LogicException::class, 'eloquent-filtering.custom_filters');
    });
});
