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
    ) {
        parent::__construct();
    }

    /**
     * @throws ValidationException
     */
    public static function fromInts(int ...$ids): self
    {
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

    protected function validate(): array
    {
        if ($this->ids === []) {
            return ['ids' => ['Sqid requires at least one integer']];
        }

        $errors = [];

        foreach ($this->ids as $id) {
            // sqid_decode() is untyped and fromSqid() only asserts array<int> by annotation.
            // @phpstan-ignore function.alreadyNarrowedType
            if (! is_int($id)) {
                $errors['ids'][] = 'Sqid only supports integers, got: '.get_debug_type($id);

                continue;
            }

            if ($id < 0) {
                $errors['ids'][] = 'Sqid only supports non-negative integers, got: '.$id;
            }
        }

        return $errors;
    }
}
