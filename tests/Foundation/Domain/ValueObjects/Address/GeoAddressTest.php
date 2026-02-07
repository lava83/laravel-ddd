<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Address\GeoAddress;

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
