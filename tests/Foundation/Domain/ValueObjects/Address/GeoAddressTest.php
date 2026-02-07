<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Address\GeoAddress;

describe('Create GeoAddress', function () {
    it('can create address from array', function () {
        $address = GeoAddress::fromArray([
            'street' => 'Main St',
            'streetNumber' => '123',
            'zipCode' => '12345',
            'city' => 'Sample City',
            'state' => 'Sample State',
            'county' => 'Sample County',
            'district' => 'Sample District',
            'neighborhood' => 'Sample Neighborhood',
            'country' => 'Sample Country',
            'precision' => 'high',
            'createdAt' => '2024-01-01 12:00:00',
            'updatedAt' => '2024-01-02 12:00:00',
        ]);

        expect($address)->toBeInstanceOf(GeoAddress::class)
        ->and($address->street())->toBe('Main St')
            ->and($address->streetNumber())->toBe('123')
            ->and($address->zipCode())->toBe('12345')
            ->and($address->city())->toBe('Sample City')
            ->and($address->state())->toBe('Sample State')
            ->and($address->county())->toBe('Sample County')
            ->and($address->district())->toBe('Sample District')
            ->and($address->neighborhood())->toBe('Sample Neighborhood')
            ->and($address->country())->toBe('Sample Country')
            ->and($address->precision())->toBe('high')
            ->and($address->createdAt()->toDateTimeString())->toBe('2024-01-01 12:00:00')
            ->and($address->updatedAt()->toDateTimeString())->toBe('2024-01-02 12:00:00');
    });

    it('creates a geo address if only required fields are provided', function () {
        $address = GeoAddress::fromArray([
            'country' => 'Sample Country',
            'precision' => 'high',
        ]);

        expect($address)->toBeInstanceOf(GeoAddress::class)
            ->and($address->street())->toBeNull()
            ->and($address->streetNumber())->toBeNull()
            ->and($address->zipCode())->toBeNull()
            ->and($address->city())->toBeNull()
            ->and($address->state())->toBeNull()
            ->and($address->county())->toBeNull()
            ->and($address->district())->toBeNull()
            ->and($address->neighborhood())->toBeNull()
            ->and($address->country())->toBe('Sample Country')
            ->and($address->precision())->toBe('high')
            ->and($address->createdAt())->not()->toBeNull()
            ->and($address->updatedAt())->not()->toBeNull();
    });

    it('throws an error if required fields are missing', function () {
        $this->expectException(InvalidArgumentException::class);

        GeoAddress::fromArray([
            'street' => 'Main St',
            'streetNumber' => '123',
            'zipCode' => '12345',
            'city' => 'Sample City',
            'state' => 'Sample State',
            'county' => 'Sample County',
            'district' => 'Sample District',
            'neighborhood' => 'Sample Neighborhood',
            // Missing country and precision
        ]);
    });

    it('has the correct string representation', function () {
        $address = GeoAddress::fromArray([
            'street' => 'Main St',
            'streetNumber' => '123',
            'zipCode' => '12345',
            'city' => 'Sample City',
            'state' => 'Sample State',
            'county' => 'Sample County',
            'district' => 'Sample District',
            'neighborhood' => 'Sample Neighborhood',
            'country' => 'Sample Country',
            'precision' => 'high',
        ]);

        expect((string) $address)->toBe('Main St 123, 12345 Sample City, Sample State, Sample County, Sample District, Sample Neighborhood, Sample Country');
    });

    it('has the correct array representation', function () {
        $address = GeoAddress::fromArray([
            'street' => 'Main St',
            'streetNumber' => '123',
            'zipCode' => '12345',
            'city' => 'Sample City',
            'state' => 'Sample State',
            'county' => 'Sample County',
            'district' => 'Sample District',
            'neighborhood' => 'Sample Neighborhood',
            'country' => 'Sample Country',
            'precision' => 'high',
            'createdAt' => '2024-01-01 12:00:00',
            'updatedAt' => '2024-01-02 12:00:00',
        ]);

        expect($address->jsonSerialize())->toBe([
            'street' => 'Main St',
            'street_number' => '123',
            'zip_code' => '12345',
            'city' => 'Sample City',
            'state' => 'Sample State',
            'county' => 'Sample County',
            'district' => 'Sample District',
            'neighborhood' => 'Sample Neighborhood',
            'country' => 'Sample Country',
            'precision' => 'high',
            'created_at' => '2024-01-01T12:00:00+00:00',
            'updated_at' => '2024-01-02T12:00:00+00:00',
        ]);
    });
});
