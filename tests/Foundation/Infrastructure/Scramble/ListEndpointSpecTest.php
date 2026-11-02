<?php

declare(strict_types=1);

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\ScrambleServiceProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Lava83\LaravelDdd\LaravelDddServiceProvider;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble\PackageClasses;
use Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble\SpecListController;

/**
 * The OpenAPI document of two list endpoints, as a consumer gets it: Scramble
 * loaded before the package provider, the package read like a vendor package.
 *
 * @return array<string, mixed>
 */
function listEndpointSpec(): array
{
    app()->register(ScrambleServiceProvider::class);
    app()->register(LaravelDddServiceProvider::class, force: true);

    Scramble::infer()->configure()->buildDefinitionsUsingReflectionFor([new PackageClasses]);
    Scramble::configure()->routes(fn (RoutingRoute $route): bool => str_starts_with($route->uri, 'api/'));

    Route::get('api/full', [SpecListController::class, 'full']);
    Route::get('api/filtered', [SpecListController::class, 'filtered']);

    $document = app(Generator::class)->setThrowExceptions(true)(Scramble::getGeneratorConfig(Scramble::DEFAULT_API));

    // As JSON, the way a client reads it: `{}` for an empty schema, not a stdClass.
    /** @var array<string, mixed> */
    return json_decode((string) json_encode($document), true);
}

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
                    'properties' => ['type' => ['type' => 'string'], 'target' => ['type' => 'string'], 'value' => []],
                    'required' => ['type'],
                ]],
            ]]);
    })->with([
        'with rules of the request on filters.*' => ['/full'],
        'with the rules of the trait only' => ['/filtered'],
    ]);

    it('documents facets[] as an array of the facets', function (): void {
        expect($this->parameters['facets[]']['description'])->toStartWith('The attributes to count')
            ->and($this->parameters['facets[]']['schema'])->toBe([
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/ListTestFacet'],
            ]);
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
