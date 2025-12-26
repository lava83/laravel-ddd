<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Entities;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\MongoObjectId;
use Lava83\LaravelDdd\Domain\ValueObjects\Identity\Uuid;
use LogicException;
use ReflectionClass;
use Stringable;

/**
 * Base class for all entities (both aggregate roots and child entities)
 * Contains common entity functionality without domain event handling
 */
abstract class Entity implements Stringable
{
    /** @var Collection<string, mixed> */
    protected Collection $dirty;

    public function __construct(
        protected CarbonImmutable $createdAt = new CarbonImmutable(),
        protected ?CarbonImmutable $updatedAt = null,
        protected int $version = 0,
    ) {
        $this->dirty = collect();
    }

    /**
     * Clone protection - entities should not be cloned carelessly
     */
    protected function __clone()
    {
        // Keep the same timestamps and version on clone
        // Child classes can override this behavior
    }

    /**
     * String representation for debugging
     */
    public function __toString(): string
    {
        return sprintf(
            '%s[id=%s, version=%d]',
            static::class,
            $this->id()->value(),
            $this->version
        );
    }

    /**
     * Get the entity's unique identifier
     * Must be implemented by concrete entities
     *
     * @todo here we expect only an Id not the types of it
     */
    abstract public function id(): Uuid|MongoObjectId;

    abstract public static function fromState(Model $state): self;

    /**
     * Compare entities by ID for equality
     */
    public function equals(self $other): bool
    {
        if ($other::class !== static::class) {
            return false;
        }

        return $this->id()->equals($other->id());
    }

    /**
     * Timestamps Management
     */
    public function createdAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): CarbonImmutable
    {
        return $this->updatedAt ?? CarbonImmutable::now();
    }

    /**
     * Optimistic Locking Support
     */
    public function version(): int
    {
        return $this->version;
    }

    public function hydrate(
        Model $model,
    ): void {
        $this->createdAt = CarbonImmutable::parse($model->created_at);
        $this->updatedAt = $model->updated_at ? CarbonImmutable::parse($model->updated_at) : null;
        $this->version = $model->version;
    }

    /**
     * Convert entity to array for persistence
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id()->value(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'version' => $this->version,
        ];
    }

    /**
     * Check if entity was recently created (within last minute)
     */
    public function isRecentlyCreated(): bool
    {
        $oneMinuteAgo = CarbonImmutable::now()->subMinute();

        return $this->createdAt >= $oneMinuteAgo;
    }

    /**
     * Check if entity was recently updated (within last minute)
     */
    public function isRecentlyUpdated(): bool
    {
        if (!$this->updatedAt instanceof CarbonImmutable) {
            return false;
        }

        $oneMinuteAgo = CarbonImmutable::now()->subMinute();

        return $this->updatedAt >= $oneMinuteAgo;
    }

    /**
     * Get entity age in seconds
     */
    public function ageInSeconds(): int
    {
        return CarbonImmutable::now()->getTimestamp() - $this->createdAt->getTimestamp();
    }

    /**
     * Check if entity is older than specified duration
     */
    public function isOlderThan(string $duration): bool
    {
        $threshold = CarbonImmutable::now()->sub($duration);

        return $this->createdAt < $threshold;
    }

    /**
     * Validate entity state
     * Override in child classes for specific validation
     *
     * @return array<string>
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->id()->value() === '' || $this->id()->value() === '0') {
            $errors[] = 'Entity must have an ID';
        }

        return $errors;
    }

    /**
     * Check if entity is valid
     */
    public function isValid(): bool
    {
        return $this->validate() === [];
    }

    /**
     * Get entity metadata for auditing
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return [
            'entity_type' => static::class,
            'entity_id' => $this->id()->value(),
            'version' => $this->version,
            'created_at' => $this->createdAt()->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt()->format(DateTimeImmutable::ATOM),
            'age_seconds' => $this->ageInSeconds(),
        ];
    }

    public function isDirty(): bool
    {
        return $this->dirty->isNotEmpty();
    }

    public function dirty(): Collection
    {
        return $this->dirty;
    }

    protected function touch(): void
    {
        $this->updatedAt = CarbonImmutable::now();

        $this->version++;
    }

    /**
     * Helper method for child entities to update themselves
     *
     * @param  array<string, mixed>  $changes  Key-value pairs of changes made to the aggregate
     */
    protected function updateEntity(array $changes): Collection
    {
        $changes = $this->collectChanges($changes);

        if ($changes->isEmpty()) {
            return $changes;
        }

        $this->applyChanges($changes);
        $this->touch();

        return $changes;
    }

    protected function collectChanges(array $newValues): Collection
    {
        $this->resetDirty();

        foreach ($newValues as $property => $newValue) {
            $currentValue = $this->$property;

            if ($this->hasChanged($currentValue, $newValue)) {
                $this->dirty->put('old_' . $property, $currentValue);
                $this->dirty->put('new_' . $property, $newValue);
            }
        }

        return $this->dirty;
    }

    protected function hasChanged(mixed $current, mixed $new): bool
    {
        if (is_object($current) && method_exists($current, '__toString')) {
            return (string) $current !== (string) $new;
        }

        return $current !== $new;
    }

    protected function applyChangesByPropertyMap(array $propertyMap, Collection $changes): void
    {
        foreach ($propertyMap as $property => $setter) {
            if ($changes->has('new_' . $property)) {
                $setter($changes->get('new_' . $property));
            }
        }
    }

    protected function updateDirtyEntity(): void
    {
        throw new LogicException('updateDirtyEntity must be implemented in child classes to apply changes');
    }

    /**
     * Applies changes from a collection to the aggregate's properties using reflection
     * Automatically maps properties based on naming convention
     *
     * @param  Collection  $changes  Collection with keys like 'new_{propertyName}'
     * @param  array<string, callable>  $customSetters  Optional custom setters for specific properties
     */
    protected function applyChanges(Collection $changes, array $customSetters = []): void
    {
        $excludedProperties = ['id', 'version', 'createdAt', 'updatedAt', 'domainEvents'];

        $reflectionClass = new ReflectionClass($this);
        $constructor = $reflectionClass->getConstructor();

        if (! $constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            // Only process promoted properties
            if (! $parameter->isPromoted()) {
                continue;
            }

            $propertyName = $parameter->getName();

            // Skip excluded properties
            if (in_array($propertyName, $excludedProperties, true)) {
                continue;
            }

            $changeKey = 'new_' . $propertyName;

            if (! $changes->has($changeKey)) {
                continue;
            }

            $value = $changes->get($changeKey);

            // Use custom setter if provided
            if (isset($customSetters[$propertyName])) {
                $customSetters[$propertyName]($value);

                continue;
            }

            // Apply value directly
            $reflectionClass->getProperty($propertyName)->setValue($this, $value);
        }
    }

    private function resetDirty(): void
    {
        $this->dirty = collect();
    }
}
