<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Infrastructure\Models\Filter\Filters\Enums;

enum FilterType: string
{
    case Equal = '$eq';

    case NotEqual = '$notEq';

    case Between = '$between';

    case NotBetween = '$notBetween';

    case BetweenColumns = '$betweenColumns';

    case NotBetweenColumns = '$notBetweenColumns';

    case GreaterThanEqualTo = '$gte';

    case GreaterThan = '$gt';
}
