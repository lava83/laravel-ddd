<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\Events\AggregateTestRenamed;
use ReflectionException;

/**
 * Aggregate with a real invariant, to exercise the validation gate of
 * updateAggregateRoot().
 *
 * @extends Aggregate<AggregateTestModel, EntityTestId>
 */
final class ValidatingAggregate extends Aggregate
{
    public function __construct(
        protected readonly EntityTestId $id,
        protected string $name,
    ) {
        parent::__construct();
    }

    public static function fromState(Model $state): static
    {
        return new self(
            EntityTestId::fromString((string) $state->getAttribute('id')),
            (string) $state->getAttribute('name'),
        );
    }

    public function id(): EntityTestId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @throws ReflectionException
     * @throws ValidationException
     */
    public function rename(string $name): void
    {
        $this->updateAggregateRoot(['name' => $name], AggregateTestRenamed::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function validate(): array
    {
        if (trim($this->name) === '') {
            return ['name' => ['Name is required']];
        }

        return [];
    }
}
