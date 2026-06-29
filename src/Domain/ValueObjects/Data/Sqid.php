<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Data;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

final class Sqid extends ValueObject
{
    /**
     * @param  array<int>  $ids
     */
    private function __construct(
        private readonly array $ids,
    ) {}

    /**
     * @throws ValidationException
     */
    public static function fromInts(int ...$ids): self
    {
        if ($ids === []) {
            throw new ValidationException('Sqid requires at least one integer');
        }

        foreach ($ids as $id) {
            if ($id < 0) {
                throw new ValidationException('Sqid only supports non-negative integers, got: '.$id);
            }
        }

        return new self(array_values($ids));
    }

    /**
     * @throws ValidationException
     */
    public static function fromSqid(string $sqid): self
    {
        $decoded = sqid_decode($sqid);

        if ($decoded->isEmpty()) {
            throw new ValidationException('Invalid or non-canonical sqid: '.$sqid);
        }

        /** @var array<int> $ids */
        $ids = $decoded->values()->all();

        return new self($ids);
    }

    /**
     * @return array<int>
     */
    public function ids(): array
    {
        return $this->ids;
    }

    /**
     * @return Collection<int, int>
     */
    public function collect(): Collection
    {
        return collect($this->ids);
    }

    public function value(): string
    {
        return sqid_encode($this->ids);
    }

    public function toString(): string
    {
        return $this->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }

    public function equals(self $other): bool
    {
        return $this->ids === $other->ids;
    }

    public function jsonSerialize(): string
    {
        return $this->value();
    }
}
