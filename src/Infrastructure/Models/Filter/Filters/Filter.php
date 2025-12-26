<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Illuminate\Support\Collection;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts\FilterContract;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;

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
     *     value: array|string
     * }
     */
    public function toArray(): array
    {
        $value = $this->value();

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        /** @var array{
         *     type: string,
         *     target: string,
         *     value: array|string
         * } $array
         */
        return [
            'type' => $this->type->value,
            'target' => $this->target(),
            'value' => $value,
        ];
    }
}
