<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api;

use Lava83\LaravelDdd\Infrastructure\Models\RemoteModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\CounterTestSubject;

/**
 * API-backed state for CounterTestSubject: integer key assigned by the API,
 * no table.
 */
final class ApiCounterTestModel extends RemoteModel
{
    /** @var class-string<CounterTestSubject> */
    protected ?string $entityClassName = CounterTestSubject::class;
}
