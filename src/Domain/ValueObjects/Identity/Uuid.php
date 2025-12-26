<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use DateTimeInterface;
use JsonSerializable;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use Stringable;

// @todo this is only a base class without specification of UUID or whatever.

class Uuid implements JsonSerializable, Stringable
{
    protected string $prefix = '';

    private readonly UuidInterface $value;

    /**
     * @throws ValidationException
     */
    final public function __construct(string $value)
    {
        if (blank($this->prefix)) {
            throw new ValidationException('Prefix must be set in the child class');
        }

        $this->validate($value);
        $this->value = RamseyUuid::fromString($value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function generate(): static
    {
        return new static(RamseyUuid::uuid7(now())->toString());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public static function fromUuid(UuidInterface $uuid): static
    {
        return new static($uuid->toString());
    }

    public static function fromBytes(string $bytes): static
    {
        return new static(RamseyUuid::fromBytes($bytes)->toString());
    }

    public static function fromPrefixed(string $prefixedId): static
    {
        $value = str($prefixedId);

        if ($value->contains('_') === false) {
            throw new ValidationException('Prefixed ID must contain underscore separator');
        }

        try {
            return new static($value->afterLast('_')->toString());
        } catch (ValidationException) {
            throw new ValidationException('Invalid prefixed ID format');
        }
    }

    public static function extractPrefix(string $prefixedId): string
    {
        $value = str($prefixedId);

        if ($value->contains('_') === false) {
            throw new ValidationException('Prefixed ID must contain underscore separator');
        }

        return $value->beforeLast('_')->toString();
    }

    public static function validatePrefix(string $prefixedId, string $expectedPrefix): bool
    {
        try {
            return self::extractPrefix($prefixedId) === $expectedPrefix;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @return array<Uuid>
     */
    public static function createMany(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = static::generate();
        }

        return $ids;
    }

    /**
     * @param  array<string>  $values
     *
     * @return array<static>
     */
    public static function fromArray(array $values): array
    {
        return array_map(fn (string $value): static => new static($value), $values);
    }

    /**
     * @param  array<Uuid>  $ids
     *
     * @return array<string>
     */
    public static function toStringArray(array $ids): array
    {
        return array_map(fn (Uuid $id): string => $id->value(), $ids);
    }

    public function value(): string
    {
        return $this->value->toString();
    }

    public function uuid(): UuidInterface
    {
        return $this->value;
    }

    public function bytes(): string
    {
        return $this->value->getBytes();
    }

    public function hex(): string
    {
        return $this->value->getHex()->toString();
    }

    public function equals(Uuid $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function toString(): string
    {
        return $this->value();
    }

    public function jsonSerialize(): string
    {
        return $this->value();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->value(),
            'hex' => $this->hex(),
        ];
    }

    /**
     * Compare IDs for sorting purposes
     */
    public function compareTo(Uuid $other): int
    {
        return $this->uuid()->compareTo($other->uuid());
    }

    /**
     * Check if this ID comes before another ID (useful for ordering)
     */
    public function isBefore(Uuid $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Check if this ID comes after another ID (useful for ordering)
     */
    public function isAfter(Uuid $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Get the timestamp component from UUID v1 (if applicable)
     * Returns null for UUID v4 and higher
     */
    public function timestamp(): ?DateTimeInterface
    {
        return $this->value->getDateTime();
    }

    /**
     * Get UUID version
     */
    public function version(): ?int
    {
        return $this->value->getVersion();
    }

    /**
     * Check if this is a nil/empty UUID
     */
    public function isNil(): bool
    {
        return (string) $this->value === RamseyUuid::NIL;
    }

    /**
     * Get a shortened version of the ID (first 8 characters)
     * Useful for logging or display purposes
     */
    public function shortId(): string
    {
        return substr($this->value(), 0, 8);
    }

    /**
     * Create a prefixed version for different entity types
     * Example: emp_550e8400-e29b-41d4-a716-446655440000
     */
    public function withPrefix(): string
    {
        return sprintf('%s_%s', $this->prefix, $this->value());
    }

    public function referenceNumber(): string
    {
        $hex = $this->hex();
        $numbers = '';

        for ($i = 0; $i < 8; $i++) {
            $numbers .= hexdec($hex[$i]) % 10;
        }

        return str($this->prefix)
            ->append('-')
            ->append((string) str($numbers)->substr(0, 4))
            ->append('-')
            ->append((string) str($numbers)->substr(4, 4))
            ->upper()
            ->toString();
    }

    public function logId(): string
    {
        return str($this->shortId())
            ->substr(0, 4)
            ->append('****')
            ->toString();
    }

    public function displayId(): string
    {
        return str($this->withPrefix())
            ->upper()
            ->replace('_', '-')
            ->toString();
    }

    /**
     * @throws ValidationException
     */
    private function validate(string $value): void
    {
        if (blank($value)) {
            throw new ValidationException('Id cannot be empty');
        }

        if (! RamseyUuid::isValid($value)) {
            throw new ValidationException('Invalid UUID format: '.$value);
        }

        $uuid = RamseyUuid::fromString($value);

        if (
            $value !== RamseyUuid::NIL
            && $uuid->getVersion() < 4
        ) {
            throw new ValidationException('Only UUID version 4 or higher are allowed, got version: '.$uuid->getVersion());
        }
    }
}
