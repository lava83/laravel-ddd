<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts\FilterContract;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\FilterValueNotValid;

abstract class Filter implements FilterContract
{
    protected FilterType $type;

    public function type(): FilterType
    {
        return $this->type;
    }

    /**
     * @return array{
     *     type: string,
     *     target: string,
     *     value: array|string|int|float|bool
     * }
     * @throws FilterValueNotValid
     */
    public function toArray(): array
    {
        if (!$this->valueIsValid()) {
            throw FilterValueNotValid::make($this->value());
        }

        $value = $this->value();

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        return [
            'type' => $this->type->value,
            'target' => $this->target(),
            'value' => $value,
        ];
    }

    abstract protected function valueIsValid(): bool;
}
