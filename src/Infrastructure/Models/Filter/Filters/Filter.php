<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters;

use Illuminate\Support\Collection;use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Contracts\FilterContract;use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums\FilterType;
use Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Exceptions\NoFilterTypeDeclared;

abstract class Filter implements FilterContract
{
    protected ?FilterType $type = null;

    /**
     * @throws NoFilterTypeDeclared
     */
    public function __construct()
    {
        if ($this->type === null) {
            throw new NoFilterTypeDeclared("No filter type declared.");
        }
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'target' => $this->target(),
            'value' => $this->value() instanceof Collection ? $this->value()->toArray() : $this->value(),
        ];
    }

    public function type(): FilterType
    {
        return $this->type;
    }
}
