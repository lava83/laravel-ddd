<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects;

use JsonSerializable;
use Stringable;

abstract class ValueObject implements JsonSerializable, Stringable {}
