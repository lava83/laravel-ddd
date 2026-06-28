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
    /**
     * @var Fluent<string, mixed>
     */
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

    /**
     * @throws ValidationException
     */
    public static function fromString(string $json): self
    {
        $trimmed = trim($json);

        return new self($trimmed);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     * @throws JsonException
     */
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

    /**
     * @return Fluent<string, mixed>
     */
    public function data(): Fluent
    {
        return $this->data;
    }

    public function toString(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data->collect()->toArray();
    }

    /**
     * @return Collection<string, mixed>
     */
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

    /**
     * @throws JsonException
     * @throws ValidationException
     */
    public function set(string $key, mixed $value): self
    {
        $newData = fluent($this->data->toArray());
        $newData->set($key, $value);

        return self::fromArray($newData->toArray());
    }

    /**
     * @throws ValidationException
     * @throws JsonException
     */
    public function merge(self $other): self
    {
        $mergedData = $this->toCollection()->merge($other->toCollection());

        return self::fromArray($mergedData->toArray());
    }

    /**
     * @throws ValidationException
     * @throws JsonException
     */
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

    /**
     * @return Collection<int, string>
     */
    public function keys(): Collection
    {
        return $this->data->collect()->keys();
    }

    /**
     * @return Collection<int, mixed>
     */
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
     * @throws ValidationException
     */
    public function snakeCaseKeys(): self
    {
        return self::fromArray(
            $this->data
                ->collect()
                ->mapWithKeys(
                    /**
                     * @return array<string, mixed>
                     */
                    fn (mixed $value, int|string $key) => [str((string) $key)->snake()->toString() => $value],
                )
                ->toArray(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function removeNestedValue(array &$array, string $key): void
    {
        $keys = str($key)->explode('.');
        $current = &$array;

        /**
         * @var string $nestedKey
         */
        foreach ($keys->take(-1) as $nestedKey) {
            if (! isset($current[$nestedKey]) || ! is_array($current[$nestedKey])) {
                return;
            }

            $current = &$current[$nestedKey];
        }

        if (is_string($keys->last())) {
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

        if (! json_validate($value)) {
            throw new ValidationException('Invalid JSON: '.json_last_error_msg());
        }
    }
}
