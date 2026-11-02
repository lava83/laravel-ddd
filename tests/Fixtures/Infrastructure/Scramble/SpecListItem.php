<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Scramble;

final readonly class SpecListItem
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
