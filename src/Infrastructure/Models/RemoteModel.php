<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models;

use Lava83\LaravelDdd\Domain\Entities\Entity;
use LogicException;

/**
 * State of an aggregate that lives behind an API instead of a table.
 *
 * The package hands a `Model` to Aggregate::fromState() and hydrate(), so the
 * API payload is wrapped in one. It is a plain attribute bag: it never
 * touches the database, and anything that would is refused.
 *
 * @extends Model<Entity<*, *>>
 *
 * @phpstan-consistent-constructor
 */
abstract class RemoteModel extends Model
{
    /**
     * @param  array<string, mixed>  $attributes  values exactly as they should be read back
     */
    public static function fromAttributes(array $attributes): static
    {
        $model = new static;
        $model->setRawAttributes($attributes, true);
        $model->exists = true;

        return $model;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): never
    {
        throw $this->databaseAccessRefused('save');
    }

    public function delete(): never
    {
        throw $this->databaseAccessRefused('delete');
    }

    public function newQuery(): never
    {
        throw $this->databaseAccessRefused('query');
    }

    private function databaseAccessRefused(string $operation): LogicException
    {
        return new LogicException(sprintf(
            '%s lives behind an API; it cannot %s the database.',
            static::class,
            $operation,
        ));
    }
}
