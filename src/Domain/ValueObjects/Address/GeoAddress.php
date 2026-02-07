<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Address;

use Carbon\CarbonImmutable;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class GeoAddress extends ValueObject
{
    public function __construct(
        private readonly ?string $street,
        private readonly ?string $streetNumber,
        private readonly ?string $zipCode,
        private readonly ?string $city,
        private readonly ?string $state,
        private readonly ?string $county,
        private readonly ?string $district,
        private readonly ?string $neighborhood,
        private readonly string $country,
        private readonly string $precision,
        private readonly CarbonImmutable $createdAt,
        private readonly CarbonImmutable $updatedAt,
    ) {}

    /**
     * @param array{
     *     street?: ?string,
     *     streetNumber?: ?string,
     *     zipCode?: ?string,
     *     city?: ?string,
     *     state?: ?string,
     *     county?: ?string,
     *     district?: ?string,
     *     neighborhood?: ?string,
     *     country: string,
     *     precision: string,
     *     createdAt?: string,
     *     updatedAt?: string,
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['street'] ?? null,
            $data['streetNumber'] ?? null,
            $data['zipCode'] ?? null,
            $data['city'] ?? null,
            $data['state'] ?? null,
            $data['county'] ?? null,
            $data['district'] ?? null,
            $data['neighborhood'] ?? null,
            $data['country'],
            $data['precision'],
            isset($data['createdAt']) ? CarbonImmutable::parse($data['createdAt']) : CarbonImmutable::now(),
            isset($data['updatedAt']) ? CarbonImmutable::parse($data['updatedAt']) : CarbonImmutable::now(),
        );
    }

    public function street(): ?string
    {
        return $this->street;
    }

    public function streetNumber(): ?string
    {
        return $this->streetNumber;
    }

    public function zipCode(): ?string
    {
        return $this->zipCode;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function county(): ?string
    {
        return $this->county;
    }

    public function district(): ?string
    {
        return $this->district;
    }

    public function neighborhood(): ?string
    {
        return $this->neighborhood;
    }

    public function country(): string
    {
        return $this->country;
    }

    public function precision(): string
    {
        return $this->precision;
    }

    public function createdAt(): CarbonImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): CarbonImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return sprintf(
            '%s %s, %s %s, %s, %s, %s, %s, %s',
            $this->street ?? '',
            $this->streetNumber ?? '',
            $this->zipCode ?? '',
            $this->city ?? '',
            $this->state ?? '',
            $this->county ?? '',
            $this->district ?? '',
            $this->neighborhood ?? '',
            $this->country,
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): mixed
    {
        return [
            'street' => $this->street,
            'street_number' => $this->streetNumber,
            'zip_code' => $this->zipCode,
            'city' => $this->city,
            'state' => $this->state,
            'county' => $this->county,
            'district' => $this->district,
            'neighborhood' => $this->neighborhood,
            'country' => $this->country,
            'precision' => $this->precision,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
