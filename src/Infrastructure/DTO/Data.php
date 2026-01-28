<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\DTO;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data as SpatieData;

abstract class Data extends SpatieData
{
    /**
     * @return array<string, Data|Collection<int, Data>|array|string|float|int|bool|null>
     */
    abstract public function mapToPersistenceLayerArray(): array;
}
