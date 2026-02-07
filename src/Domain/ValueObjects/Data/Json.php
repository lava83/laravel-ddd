<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use JsonException;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class Json extends ValueObject
{
    private readonly Fluent $data;

    /**
     * @throws ValidationException
     */
    private function __construct(
        private readonly string $value,
    ) {
        $this->validate($value);

        /**
         * @var array<string, mixed> $jsonDecodedValue
         */
        $jsonDecodedValue = json_decode($value, true);

        $this->data = fluent($jsonDecodedValue);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $json): self
    {
        $trimmed = trim($json);

        return new self($trimmed);
    }

    public static function fromArray(array $data): self
    {
        $jsonString = json_encode($data, JSON_THROW_ON_ERROR);

        return new self($jsonString);
    }

    public static function empty(): self
    {
        return new self('{}');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function data(): Fluent
    {
        return $this->data;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function toArray(): array
    {
        return $this->data->collect()->toArray();
    }

    public function toCollection(): Collection
    {
        return $this->data->collect();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->data->has($key);
    }

    public function set(string $key, mixed $value): self
    {
        $newData = fluent($this->data->toArray());
        $newData->set($key, $value);

        return self::fromArray($newData->toArray());
    }

    public function merge(self $other): self
    {
        $mergedData = $this->toCollection()->merge($other->toCollection());

        return self::fromArray($mergedData->toArray());
    }

    public function remove(string $key): self
    {
        $newData = $this->data->toArray();

        $this->removeNestedValue($newData, $key);

        return self::fromArray($newData);
    }

    public function isEmpty(): bool
    {
        return $this->data->isEmpty();
    }

    public function isNotEmpty(): bool
    {
        return $this->data->isNotEmpty();
    }

    public function keys(): Collection
    {
        return $this->data->collect()->keys();
    }

    public function values(): Collection
    {
        return collect($this->data->all());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @throws JsonException
     */
    public function snakeCaseKeys(): self
    {
        return self::fromArray(
            $this->data
                ->collect()
                ->mapWithKeys(
                    /**
                     * @param mixed $value
                     * @param int|string $key
                     * @return array<string, mixed>
                     */
                    fn(mixed $value, int|string $key) => [str((string) $key)->snake()->toString() => $value]
                )
                ->toArray(),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function removeNestedValue(array &$array, string $key): void
    {
        $keys = str($key)->explode('.');
        $current = &$array;

        /**
         * @var string $nestedKey
         */
        foreach ($keys->take(-1) as $nestedKey) {
            if (!isset($current[$nestedKey]) || !is_array($current[$nestedKey])) {
                return;
            }

            $current = &$current[$nestedKey];
        }

        if (is_string($keys->last())) {
            // @mago-expect analyzer:possibly-null-array-index
            unset($current[$keys->last()]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validate(string $value): void
    {
        if (trim($value) === '') {
            throw new ValidationException('JSON string cannot be empty');
        }

        if (!json_validate($value)) {
            throw new ValidationException('Invalid JSON: ' . json_last_error_msg());
        }
    }
}
