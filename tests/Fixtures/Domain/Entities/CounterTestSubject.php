<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\Entities;

use Lava83\LaravelDdd\Domain\Entities\Aggregate;
use Lava83\LaravelDdd\Infrastructure\Models\Model;
use Lava83\LaravelDdd\Tests\Fixtures\Domain\ValueObjects\Identity\IntegerTestId;
use ReflectionException;

/**
 * Integer-keyed aggregate fixture for the auto-increment save path.
 *
 * `id` is deliberately NOT readonly: on insert the database assigns the key and
 * Repository::persistDirtyEntity() flows it back through hydrate() ->
 * idFromPersistence(), which reassigns the promoted `id` property by reflection
 * — impossible on a readonly property once initialized.
 *
 * @extends Aggregate<CounterTestModel, IntegerTestId>
 */
final class CounterTestSubject extends Aggregate
{
    public function __construct(
        protected IntegerTestId $id,
        protected string $name,
    ) {
        parent::__construct();
    }

    /**
     * Brand-new aggregate before persistence: id 0 stands in until the database
     * assigns the real key.
     */
    public static function create(string $name): self
    {
        return new self(IntegerTestId::new(), $name);
    }

    public static function fromState(Model $state): static
    {
        $aggregate = new self(
            IntegerTestId::fromValue((int) $state->getAttribute('id')),
            (string) $state->getAttribute('name'),
        );

        $aggregate->hydrate($state);

        return $aggregate;
    }

    public function id(): IntegerTestId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @throws ReflectionException
     */
    public function rename(string $name): void
    {
        $this->updateAggregateRoot(['name' => $name]);
    }
}
