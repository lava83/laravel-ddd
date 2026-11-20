<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Lava83\LaravelDdd\Infrastructure\Scramble\FiltersParameterSchema;
use Lava83\LaravelDdd\Infrastructure\Scramble\SortParameterSchema;
use Lava83\LaravelDdd\LaravelDddServiceProvider;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble\PackageClasses;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble\SpecApiServiceProvider;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble\SpecListController;

/**
 * Two list endpoints, as a consumer has them: Scramble loaded before the
 * package provider, APIs of the consumer registered in a `boot()` before the
 * package provider has booted, and the package read like a vendor package.
 */
function bootListEndpoints(): void
{
    app()->register(ScrambleServiceProvider::class);
    app()->register(SpecApiServiceProvider::class);
    app()->register(LaravelDddServiceProvider::class, force: true);

    Scramble::infer()->configure()->buildDefinitionsUsingReflectionFor([new PackageClasses]);

    Route::get('api/full', [SpecListController::class, 'full']);
    Route::get('api/filtered', [SpecListController::class, 'filtered']);
}

/**
 * The OpenAPI document of the list endpoints in one API, as JSON.
 */
function listEndpointJson(string $api = Scramble::DEFAULT_API): string
{
    $config = Scramble::getGeneratorConfig($api)->routes(fn (RoutingRoute $route): bool => str_starts_with($route->uri, 'api/'));

    return (string) json_encode(app(Generator::class)->setThrowExceptions(true)($config));
}

/**
 * The document decoded to arrays: `{}` and `[]` both become `[]`.
 *
 * @return array<string, mixed>
 */
function listEndpointSpec(string $api = Scramble::DEFAULT_API): array
{
    /** @var array<string, mixed> */
    return json_decode(listEndpointJson($api), true);
}

beforeEach(function (): void {
    bootListEndpoints();
});

/**
 * @param  array<string, mixed>  $spec
 * @return array<string, array<string, mixed>> keyed by name
 */
function specParameters(array $spec, string $path): array
{
    return collect($spec['paths'][$path]['get']['parameters'])->keyBy('name')->all();
}

describe('The OpenAPI spec of a list endpoint', function (): void {
    beforeEach(function (): void {
        $this->spec = listEndpointSpec();
        $this->parameters = specParameters($this->spec, '/full');
    });

    it('documents every list parameter as optional', function (): void {
        expect(array_keys($this->parameters))->toEqualCanonicalizing(['per_page', 'cursor', 'filters', 'sort[]', 'search', 'facets[]']);

        foreach ($this->parameters as $parameter) {
            expect($parameter['required'] ?? false)->toBeFalse();
        }
    });

    it('documents sort[] as an array of the sort fields', function (): void {
        expect($this->parameters['sort[]']['description'])->toStartWith('The fields to sort by')
            ->and($this->parameters['sort[]']['schema'])->toEqual([
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['rank', '-rank', 'name', '-name', 'happened_at', '-happened_at']],
                'minItems' => 1,
                'uniqueItems' => true,
            ]);
    });

    it('documents filters as a JSON string or a list of filters', function (string $path): void {
        $filters = specParameters($this->spec, $path)['filters'];

        expect($filters['description'])->toStartWith('The filters of the list')
            ->and($filters['schema'])->toEqual(['anyOf' => [
                ['type' => 'string', 'contentMediaType' => 'application/json'],
                ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'target' => ['type' => 'string'],
                        'value' => ['description' => 'A value, a list of values or nested filters, as the type takes it.'],
                    ],
                    'required' => ['type'],
                ]],
            ]]);
    })->with([
        'with rules of the request on filters.*' => ['/full'],
        'with the rules of the trait only' => ['/filtered'],
    ]);

    it('documents the value of a filter as a schema object, not a list', function (): void {
        $filters = collect(json_decode(listEndpointJson())->paths->{'/full'}->get->parameters)->firstWhere('name', 'filters');

        expect($filters->schema->anyOf[1]->items->properties->value)->toBeInstanceOf(stdClass::class);
    });

    it('documents facets[] as an array of the facets', function (): void {
        $schema = $this->parameters['facets[]']['schema'];

        // Not the whole schema: Scramble 0.13.53 added `null` to the type and `uniqueItems`.
        expect($this->parameters['facets[]']['description'])->toStartWith('The attributes to count')
            ->and($schema['type'])->toContain('array')
            ->and($schema['items'])->toBe(['$ref' => '#/components/schemas/ListTestFacet']);
    });

    it('documents the default page size', function (): void {
        expect($this->parameters['per_page']['description'])->toBe('The number of entries per page, 50 if omitted.')
            ->and($this->parameters['per_page']['schema']['default'])->toBe(50);
    });

    it('documents the page with meta.facets next to the page without', function (): void {
        $pages = $this->spec['paths']['/full']['get']['responses']['200']['content']['application/json']['schema']['anyOf'];

        expect(collect($pages)->map(fn (array $page): ?array => $page['properties']['meta']['properties']['facets'] ?? null)->all())
            ->toEqualCanonicalizing([null, ['$ref' => '#/components/schemas/FacetsResource']])
            ->and($this->spec['components']['schemas'])->toHaveKey('FacetsResource');
    });
});

describe('The OpenAPI spec of a list endpoint in every API', function (): void {
    it('documents sort[] and filters once, with their schemas', function (string $api): void {
        if ($api === 'late') {
            Scramble::registerApi('late');
        }

        if ($api === 'late-by-hand') {
            Scramble::registerApi('late-by-hand')->withRuleTransformers([SortParameterSchema::class, FiltersParameterSchema::class]);
        }

        $spec = listEndpointSpec($api);
        $parameters = specParameters($spec, '/full');

        expect(collect($spec['paths']['/full']['get']['parameters'])->pluck('name')->all())
            ->toEqualCanonicalizing(['per_page', 'cursor', 'filters', 'sort[]', 'search', 'facets[]'])
            ->and($parameters['sort[]']['description'])->toStartWith('The fields to sort by')
            ->and($parameters['sort[]']['schema'])->not->toHaveKey('enum')
            ->and($parameters['sort[]']['schema']['items']['enum'])->toBe(['rank', '-rank', 'name', '-name', 'happened_at', '-happened_at'])
            ->and($parameters['filters']['description'])->toStartWith('The filters of the list')
            ->and($parameters['filters']['schema'])->toHaveKey('anyOf');
    })->with([
        'registered in boot(), before the package' => ['early'],
        'registered in boot(), with the transformers by hand' => ['early-by-hand'],
        'registered after boot' => ['late'],
        'registered after boot, with the transformers by hand' => ['late-by-hand'],
        'the default API' => [Scramble::DEFAULT_API],
    ]);
});
