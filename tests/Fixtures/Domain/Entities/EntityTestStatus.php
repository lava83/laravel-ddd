<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

/**
 * Backed enum used to exercise the enum branch of Entity::hasChanged().
 */
enum EntityTestStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
