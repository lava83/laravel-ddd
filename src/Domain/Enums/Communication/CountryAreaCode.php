<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\Enums\Communication;

enum CountryAreaCode: string
{
    case US = '+1';
    case DE = '+49';
    case FR = '+33';
    case IT = '+39';
    case ES = '+34';
    case UK = '+44';
    case AU = '+61';
    case PL = '+48';
    case BE = '+32';
    case NL = '+31';
    case CH = '+41';
    case LU = '+352';
    case AT = '+43';
    case DK = '+45';
    case NO = '+47';
    case FI = '+358';
    case SE = '+46';
    case LI = '+423';
    case PT = '+351';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $code) => $code->value, self::cases());
    }
}
