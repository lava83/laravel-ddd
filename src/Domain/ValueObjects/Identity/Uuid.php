<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Identity;

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

// @todo this is only a base class without specification of UUID or whatever.

/**
 * @property-read UuidInterface $value
 */
class Uuid extends Id
{
    protected string $prefix = '';

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function generate(): static
    {
        return new static(RamseyUuid::uuid7(now()));
    }

    /**
     * @throws ValidationException
     */
    public static function fromString(string $value): static
    {
        self::validate($value);

        return new static(RamseyUuid::fromString($value));
    }

    public static function fromUuid(Uuid $uuid): static
    {
        return new static(RamseyUuid::fromString((string) $uuid));
    }

    public static function fromBytes(string $bytes): static
    {
        return new static(RamseyUuid::fromBytes($bytes));
    }

    /**
     * @throws ValidationException
     */
    public static function fromPrefixed(string $prefixedId): static
    {
        $value = str($prefixedId);

        if ($value->contains('_') === false) {
            throw new ValidationException('Prefixed ID must contain underscore separator');
        }

        try {
            return new static(RamseyUuid::fromString((string) $value->afterLast('_')));
        } catch (Throwable) {
            throw new ValidationException('Invalid prefixed ID format');
        }
    }

    /**
     * @throws ValidationException
     */
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
     * @return array<static>
     */
    public static function fromArray(array $values): array
    {
        return array_map(fn(string $value): static => new static(RamseyUuid::fromString($value)), $values);
    }

    /**
     * @param  array<Uuid>  $ids
     * @return array<string>
     */
    public static function toStringArray(array $ids): array
    {
        return array_map(fn(Uuid $id): string => $id->value(), $ids);
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
        return (string) $this->value->getHex();
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
        return str($this->shortId())->substr(0, 4)->append('****')->toString();
    }

    public function displayId(): string
    {
        return str($this->withPrefix())->upper()->replace('_', '-')->toString();
    }

    /**
     * @throws ValidationException
     */
    private static function validate(string $value): void
    {
        if (blank($value)) {
            throw new ValidationException('Id cannot be empty');
        }

        if (!RamseyUuid::isValid($value)) {
            throw new ValidationException('Invalid UUID format: ' . $value);
        }
    }
}
