<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Tests\Fixtures\Domain\ReadModels;

use Lava83\LaravelDdd\Domain\ReadModels\ReadModel;

/**
 * Minimal concrete read model.
 *
 * ReadModel is abstract but declares no abstract members, so a subclass needs
 * only to exist in order to be instantiated and exercised.
 */
final class ReadModelTestSubject extends ReadModel {}
