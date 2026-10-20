<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use JsonException;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;
use stdClass;

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
        parent::__construct();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($value, true);

        $this->data = fluent($decoded);
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
        $jsonString = $data === [] ? '{}' : json_encode($data, JSON_THROW_ON_ERROR);

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
        $tree = $this->tree();
        data_set($tree, $key, $value);

        return self::fromTree($tree);
    }

    /**
     * @throws ValidationException
     * @throws JsonException
     */
    public function merge(self $other): self
    {
        return self::fromRoot(array_merge((array) $this->tree(), (array) $other->tree()));
    }

    /**
     * @throws ValidationException
     * @throws JsonException
     */
    public function remove(string $key): self
    {
        $tree = $this->tree();
        data_forget($tree, $key);

        return self::fromTree($tree);
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
        $snaked = [];

        foreach ((array) $this->tree() as $key => $value) {
            $snaked[str((string) $key)->snake()->toString()] = $value;
        }

        return self::fromRoot($snaked);
    }

    /**
     * The document as json_decode() reads it without assoc: objects stay objects,
     * so an empty object is written back as {} and not as [].
     */
    public function jsonSerialize(): mixed
    {
        return $this->tree();
    }

    /**
     * The document with objects as stdClass, the only decoded form that keeps an
     * empty object apart from an empty list. Changes are made on it and never
     * leave this class, toArray() and data() hand out plain arrays.
     */
    private function tree(): mixed
    {
        return json_decode($this->value);
    }

    /**
     * @param  array<array-key, mixed>  $root
     */
    private static function fromRoot(array $root): self
    {
        return self::fromTree($root === [] || ! array_is_list($root) ? (object) $root : $root);
    }

    private static function fromTree(mixed $tree): self
    {
        return new self(json_encode($tree, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws ValidationException
     */
    protected function validate(): array
    {
        if (trim($this->value) === '') {
            return ['value' => ['JSON string cannot be empty']];
        }

        if (! json_validate($this->value)) {
            return ['value' => ['Invalid JSON: '.json_last_error_msg()]];
        }

        if (! is_object($decoded = json_decode($this->value)) && ! is_array($decoded)) {
            return ['value' => ['JSON must be an object or an array']];
        }

        return [];
    }
}
