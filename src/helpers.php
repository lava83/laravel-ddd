<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Infrastructure\Contracts\EntityMapperResolverContract;

if (! function_exists('entity_mapper_resolver')) {
    function entity_mapper_resolver(): EntityMapperResolverContract
    {
        return app(EntityMapperResolverContract::class);
    }
}
