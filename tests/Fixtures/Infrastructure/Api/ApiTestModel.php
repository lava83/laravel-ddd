<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Api;

use Lava83\LaravelDdd\Infrastructure\Models\RemoteModel;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities\AggregateTestSubject;

/**
 * API-backed state for AggregateTestSubject: uuid key, no table.
 */
final class ApiTestModel extends RemoteModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    /** @var class-string<AggregateTestSubject> */
    protected ?string $entityClassName = AggregateTestSubject::class;
}
