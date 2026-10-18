<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Enums\Communication\CountryAreaCode;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Phonenumber;

describe('construction', function (): void {
    it('splits a number into country, area and subscriber part', function (
        string $input,
        CountryAreaCode $country,
        string $localAreaCode,
        string $subscriberNumber,
        string $normalised,
    ): void {
        $number = new Phonenumber($input);

        expect($number->countryAreaCode())->toBe($country)
            ->and($number->localAreaCode()->toString())->toBe($localAreaCode)
            ->and($number->subscriberNumber()->toString())->toBe($subscriberNumber)
            ->and($number->value()->toString())->toBe($normalised);
    })->with([
        'german landline' => ['+49 30 123456', CountryAreaCode::DE, '030', '123456', '+4930123456'],
        'german mobile' => ['+49 171 1234567', CountryAreaCode::DE, '0171', '1234567', '+491711234567'],
        'austrian landline' => ['+43 1 5810000', CountryAreaCode::AT, '01', '5810000', '+4315810000'],
        'swiss landline' => ['+41 44 668 18 00', CountryAreaCode::CH, '044', '6681800', '+41446681800'],
        'british landline' => ['+44 20 7946 0958', CountryAreaCode::UK, '020', '79460958', '+442079460958'],
        'french landline' => ['+33 1 42 68 53 00', CountryAreaCode::FR, '01', '42685300', '+33142685300'],
    ]);

    it('normalises a number written without spaces to the same value', function (): void {
        expect((new Phonenumber('+4930123456'))->value()->toString())
            ->toBe((new Phonenumber('+49 30 123456'))->value()->toString());
    });

    it('trims surrounding whitespace', function (): void {
        expect((new Phonenumber('  +49 30 123456  '))->value()->toString())->toBe('+4930123456');
    });

    it('keeps two numbers of the same process apart', function (): void {
        $first = new Phonenumber('+49 30 123456');
        $second = new Phonenumber('+49 40 987654');

        expect($first->value()->toString())->toBe('+4930123456')
            ->and($second->value()->toString())->toBe('+4940987654');
    });
});

describe('rejection', function (): void {
    it('rejects a national number without a country code', function (): void {
        expect(fn () => new Phonenumber('030 123456'))
            ->toThrow(ValidationException::class, 'Missing or invalid default region');
    });

    it('rejects the 00 prefix', function (): void {
        expect(fn () => new Phonenumber('0049 30 123456'))
            ->toThrow(ValidationException::class, 'Missing or invalid default region');
    });

    it('rejects a number that is too short', function (): void {
        expect(fn () => new Phonenumber('+49 1'))
            ->toThrow(ValidationException::class, 'too short');
    });

    it('rejects letters', function (): void {
        expect(fn () => new Phonenumber('+49 abc'))
            ->toThrow(ValidationException::class, 'Failed to parse phone number');
    });

    it('rejects an empty string', function (): void {
        expect(fn () => new Phonenumber(''))
            ->toThrow(ValidationException::class, 'Failed to parse phone number');
    });
});

describe('representation', function (): void {
    it('casts to its normalised value', function (): void {
        expect((string) new Phonenumber('+49 30 123456'))->toBe('+4930123456');
    });

    it('exposes its parts as an array', function (): void {
        expect((new Phonenumber('+49 30 123456'))->toArray())->toBe([
            'value' => '+4930123456',
            'country_area_code' => '+49',
            'local_area_code' => '030',
            'number' => '123456',
        ]);
    });

    it('serialises to the same array as json', function (): void {
        $number = new Phonenumber('+49 30 123456');

        expect($number->jsonSerialize())->toBe($number->toArray())
            ->and(json_encode($number))->toBe('{"value":"+4930123456","country_area_code":"+49","local_area_code":"030","number":"123456"}');
    });
});

// Known defects found while characterising Phonenumber. They describe the
// behaviour we want, not the one we have; turn each into a real test when it is
// fixed.
describe('known defects', function (): void {
    it('normalises a north american number', function (): void {
        expect((new Phonenumber('+1 650 253 0000'))->value()->toString())->toBe('+16502530000');
    })->todo('returns +1650)253-0000 with local area code (650)');

    it('rejects a country code it does not know with a ValidationException', function (): void {
        expect(fn () => new Phonenumber('+81 3 1234 5678'))->toThrow(ValidationException::class);
    })->todo('a valid number passes validation, then CountryAreaCode::from() throws a ValueError');
});
