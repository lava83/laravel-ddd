<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Enums;

enum MergeStrategy
{
    /**
     * Keep every existing filter and append the incoming ones. Nothing is
     * removed, so default filters (e.g. tenant scoping) always survive.
     */
    case KeepExisting;

    /**
     * Let an incoming filter replace existing filters that match on both
     * target and operator (type). Non-matching existing filters are kept.
     */
    case Override;
}
