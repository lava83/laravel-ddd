<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Foundation\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;

it('can convert uuid to string', function () {
    $uuid = UuidTest::generate();

    expect($uuid)->toBeInstanceOf(Uuid::class)
        ->and((string)$uuid)->toBe($uuid->toString());
});

class UuidTest extends Uuid
{
    protected string $prefix = 'entry';
}
