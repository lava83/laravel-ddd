<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Scramble;

use Dedoc\Scramble\Infer\Extensions\Event\StaticMethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\StaticMethodReturnTypeExtension;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\MethodCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Lava83\LaravelDdd\Application\Resources\FacetsResource;

/**
 * Types {@see FacetsResource::addTo()} as the collection it gets, or that
 * collection with `additional(['meta' => ['facets' => FacetsResource]])`.
 * Scramble then documents the page without and with `meta.facets`, as it does
 * for a use case that calls `additional()` itself. From its `@return` alone,
 * it sees the collection without the facets.
 */
final class FacetsResourceAddToExtension implements StaticMethodReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === FacetsResource::class;
    }

    public function getStaticMethodReturnType(StaticMethodCallEvent $event): ?Type
    {
        if ($event->getName() !== 'addTo') {
            return null;
        }

        $collection = $event->getArg('collection', 0);

        $withFacets = ReferenceTypeResolver::getInstance()->resolve($event->scope, new MethodCallReferenceType(
            $collection,
            'additional',
            [new KeyedArrayType([
                new ArrayItemType_('meta', new KeyedArrayType([
                    new ArrayItemType_('facets', new ObjectType(FacetsResource::class)),
                ])),
            ])],
        ));

        return Union::wrap([$collection, $withFacets]);
    }
}
