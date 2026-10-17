<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels;

use Lava83\LaravelDdd\Domain\ReadModels\ReadModel;

/**
 * Has `id`, but no `account_id` property: the source table sorts by `account_id`.
 */
final class CursorTestReadModel extends ReadModel
{
    public function __construct(
        public readonly int $id,
        public readonly string $owner,
    ) {}
}
