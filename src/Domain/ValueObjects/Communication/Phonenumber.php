<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Communication;

use Closure;
use Illuminate\Support\Stringable;
use JsonSerializable;
use Lava83\LaravelDdd\Domain\Enums\Communication\CountryAreaCode;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibphonenumberPhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Stringable as StringableContract;

class Phonenumber implements JsonSerializable, StringableContract
{
    private Stringable $value;

    private CountryAreaCode $countryAreaCode;

    private Stringable $localAreaCode;

    private Stringable $subscriberNumber;

    public function __construct(
        string $number,
    ) {
        $number = str($number);

        $this->validate($number);

        $this->parseNumberParts($number);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function value(): Stringable
    {
        return $this->value;
    }

    public function countryAreaCode(): CountryAreaCode
    {
        return $this->countryAreaCode;
    }

    public function localAreaCode(): Stringable
    {
        return $this->localAreaCode;
    }

    public function subscriberNumber(): Stringable
    {
        return $this->subscriberNumber;
    }

    /**
     * @return array{value: string, country_area_code: string, local_area_code: string, number: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value->toString(),
            'country_area_code' => $this->countryAreaCode->value,
            'local_area_code' => $this->localAreaCode->toString(),
            'number' => $this->subscriberNumber->toString(),
        ];
    }

    /**
     * @return array{value: string, country_area_code: string, local_area_code: string, number: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function parseNumberParts(Stringable $rawNumber): void
    {
        $number = $this->numberProto((string) $rawNumber);

        $this->countryAreaCode = CountryAreaCode::from(
            (string) str('+')->append((string) $number->getCountryCode())
        );

        $this->localAreaCode = str($this->phoneNumberUtil()->formatNationalNumberWithCarrierCode($number, null))
            ->before(' ');

        $this->subscriberNumber = str($this->phoneNumberUtil()->formatNationalNumberWithCarrierCode($number, null))
            ->after(' ')->replace(' ', '');

        $this->value = $this->buildNormalizedNumber();
    }

    private function buildNormalizedNumber(): Stringable
    {
        return str(
            $this->countryAreaCode->value.
            $this->localAreaCode->substr(1).
            $this->subscriberNumber
        );
    }

    private function validate(Stringable $number): void
    {
        $number = $number->trim();

        $validator = validator(['number' => $number], [
            'number' => [
                'required',
                fn (string $attribute, string $value, Closure $fail) => $this->validatePhoneNumber($value, $fail),
            ],
        ]);

        if ($validator->fails()) {
            throw new ValidationException('Invalid phone number format provided');
        }
    }

    private function validatePhoneNumber(
        string $value,
        Closure $fail
    ): void {
        try {
            $this->numberProto($value);
        } catch (NumberParseException $numberParseException) {
            $fail('Failed to parse phone number: ' . $numberParseException->getMessage());
        }
    }

    private function numberProto(string $value): LibphonenumberPhoneNumber
    {
        return once(function () use ($value): LibphonenumberPhoneNumber {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();

            try {
                return $phoneNumberUtil->parse($value);
            } catch (NumberParseException $numberParseException) {
                throw new ValidationException('Failed to parse phone number: ' . $numberParseException->getMessage());
            }
        });
    }

    private function phoneNumberUtil(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }
}
