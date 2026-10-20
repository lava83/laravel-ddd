<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Communication;

use Illuminate\Support\Stringable;
use Lava83\LaravelDdd\Domain\Enums\Communication\CountryAreaCode;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibphonenumberPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class Phonenumber extends ValueObject
{
    private Stringable $value;

    private CountryAreaCode $countryAreaCode;

    private Stringable $localAreaCode;

    private Stringable $subscriberNumber;

    public function __construct(string $number)
    {
        $this->value = str($number);

        parent::__construct();

        $this->parseNumberParts($this->value);
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

        $this->countryAreaCode = CountryAreaCode::from((string) str('+')->append((string) $number->getCountryCode()));

        $national = $this->phoneNumberUtil()->formatNationalNumberWithCarrierCode($number, null);

        $this->localAreaCode = str(preg_replace('/[^\d]/', '', str($national)->before(' ')->toString()));

        $this->subscriberNumber = str(preg_replace('/[^\d]/', '', str($national)->after(' ')->toString()));

        $this->value = str($this->phoneNumberUtil()->format($number, PhoneNumberFormat::E164));
    }

    protected function validate(): array
    {
        try {
            $number = $this->numberProto($this->value->trim()->toString());
        } catch (ValidationException $validationException) {
            return ['value' => [$validationException->getMessage()]];
        }

        if (CountryAreaCode::tryFrom('+'.$number->getCountryCode()) === null) {
            return ['value' => ['Unsupported country code: +'.$number->getCountryCode()]];
        }

        return [];
    }

    private function numberProto(string $value): LibphonenumberPhoneNumber
    {
        return once(function () use ($value): LibphonenumberPhoneNumber {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();

            try {
                return $phoneNumberUtil->parse($value);
            } catch (NumberParseException $numberParseException) {
                throw new ValidationException('Failed to parse phone number: '.$numberParseException->getMessage());
            }
        });
    }

    private function phoneNumberUtil(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }
}
