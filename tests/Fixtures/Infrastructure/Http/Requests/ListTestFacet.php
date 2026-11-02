<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Http\Requests;

enum ListTestFacet: string
{
    case Status = 'status';
    case Type = 'type';
}
