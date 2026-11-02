<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FilteredListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\FullListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\ListRequests;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\UnwiredValidationDataListRequest;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests\WiredValidationDataListRequest;

describe('AcceptsFilters forms', function (): void {
    it('reads the same builder from a query array and from JSON', function (): void {
        $filters = [['type' => '$eq', 'target' => 'status_id', 'value' => '2'], ['type' => '$in', 'target' => 'type_id', 'value' => ['1', '3']]];

        $fromArray = ListRequests::resolve(FilteredListRequest::class, ['filters' => $filters])->filters();
        $fromJson = ListRequests::resolve(FilteredListRequest::class, ['filters' => json_encode($filters)])->filters();

        expect($fromJson->toArray())->toBe($fromArray->toArray())
            ->and($fromJson->toArray())->toBe($filters);
    });

    it('keeps int and bool from JSON, which bracket notation turns into strings', function (): void {
        $filters = '[{"type":"$gte","target":"rank","value":2},{"type":"$null","target":"score","value":true}]';

        expect(ListRequests::resolve(FilteredListRequest::class, ['filters' => $filters])->filters()->toArray())->toBe([
            ['type' => '$gte', 'target' => 'rank', 'value' => 2],
            ['type' => '$null', 'target' => 'score', 'value' => true],
        ]);
    });

    it('lets a JSON string in the body win over an array in the query', function (): void {
        $request = ListRequests::resolve(
            FilteredListRequest::class,
            ['filters' => [['type' => '$eq', 'target' => 'name', 'value' => 'query']]],
            ['filters' => '[{"type":"$eq","target":"name","value":"body"}]'],
        );

        expect($request->filters()->toArray())->toBe([['type' => '$eq', 'target' => 'name', 'value' => 'body']]);
    });

    it('lets a JSON string in the query win over an array in the body', function (): void {
        $request = ListRequests::resolve(
            FilteredListRequest::class,
            ['filters' => '[{"type":"$eq","target":"name","value":"query"}]'],
            ['filters' => [['type' => '$eq', 'target' => 'name', 'value' => 'body']]],
        );

        expect($request->filters()->toArray())->toBe([['type' => '$eq', 'target' => 'name', 'value' => 'query']]);
    });

    it('validates the form that wins', function (): void {
        expect(ListRequests::errors(
            FilteredListRequest::class,
            ['filters' => '[{"type":"$nope","target":"name","value":"query"}]'],
            ['filters' => [['type' => '$eq', 'target' => 'name', 'value' => 'body']]],
        ))->toHaveKey('filters');
    });

    it('reads no filters from a missing, blank or empty parameter', function (array $query): void {
        expect(ListRequests::resolve(FilteredListRequest::class, $query)->filters()->toArray())->toBe([]);
    })->with([
        'missing' => [[]],
        'blank' => [['filters' => '']],
        'empty JSON list' => [['filters' => '[]']],
    ]);
});

describe('AcceptsFilters errors', function (): void {
    it('rejects filters on the filters field', function (mixed $filters, string $message): void {
        expect(ListRequests::errors(FilteredListRequest::class, ['filters' => $filters]))
            ->toBe(['filters' => [$message]]);
    })->with([
        'invalid JSON' => ['[{"type":', 'The filters must be a JSON list of filters.'],
        'a JSON object' => ['{"type":"$eq","target":"name","value":"a"}', 'The filters must be a JSON list of filters.'],
        'a JSON scalar' => ['5', 'The filters must be a JSON list of filters.'],
        'keyed filters' => [['a' => ['type' => '$eq', 'target' => 'name', 'value' => 'a']], 'The filters must be a list of filters.'],
        'a missing key' => ['[{"type":"$eq","value":"a"}]', 'The filter array is missing the required "target" key.'],
        'an unknown operator' => ['[{"type":"$nope","target":"name","value":"a"}]', 'The filter type "$nope" is not a known filter type.'],
        'a value of the wrong type' => [[['type' => '$gte', 'target' => 'rank', 'value' => '2']], 'The value for filter type "$gte" has an invalid type (string).'],
        'an invalid value' => ['[{"type":"$in","target":"rank","value":[]}]', 'The filter value "[]" is not valid.'],
        'the search type' => ['[{"type":"$listTestSearch","value":"anna"}]', 'The filter type "$listTestSearch" is not a known filter type.'],
    ]);

    it('rejects a filter that is not an array on its own field', function (): void {
        expect(ListRequests::errors(FilteredListRequest::class, ['filters' => '["a"]']))->toHaveKey('filters.0')
            ->and(ListRequests::errors(FilteredListRequest::class, ['filters' => '[null]']))->toHaveKey('filters.0');
    });

    it('applies a rule of the request on filters.* to both forms', function (mixed $filters): void {
        expect(ListRequests::errors(FullListRequest::class, ['filters' => $filters]))->toHaveKey('filters.0.target');
    })->with([
        'query array' => [[['type' => '$eq', 'target' => '$listTestScope', 'value' => '1']]],
        'JSON' => ['[{"type":"$eq","target":"$listTestScope","value":1}]'],
    ]);
});

describe('AcceptsFilters validationData()', function (): void {
    it('decodes JSON for a request that wires its own validationData()', function (): void {
        $request = ListRequests::resolve(WiredValidationDataListRequest::class, ['filters' => '[{"type":"$eq","target":"name","value":"a"}]']);

        expect($request->filters()->toArray())->toBe([['type' => '$eq', 'target' => 'name', 'value' => 'a']])
            ->and(ListRequests::errors(WiredValidationDataListRequest::class, ['filters' => '[{"type":"$eq","target":"secret","value":"a"}]']))
            ->toHaveKey('filters.0.target');
    });

    it('refuses filters of a request that forgot to decode them', function (): void {
        $request = ListRequests::resolve(UnwiredValidationDataListRequest::class);

        expect(fn () => $request->filters())->toThrow(LogicException::class, 'withDecodedFilters');
    });
});
