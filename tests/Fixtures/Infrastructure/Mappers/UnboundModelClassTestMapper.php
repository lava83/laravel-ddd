<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Infrastructure\Mappers;

/**
 * Leaves $modelClass at its inherited null default, exercising the guard's
 * "not defined" branch.
 */
final class UnboundModelClassTestMapper extends ModelClassTestMapper {}
