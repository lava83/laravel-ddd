<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Email;

// Not covered on purpose, the decision on them is still open: the business rules
// in the constructor (blocked domains, test patterns, noreply), the extra API
// (hRScore, gravatarUrl, encrypt, isRoleBased, the two placeholders) and the
// questions of format (localhost, IP literals, umlauts, 65 character local parts).

describe('construction', function (): void {
    it('accepts a plain address', function (): void {
        expect(Email::fromString('anna@example.com')->value()->toString())->toBe('anna@example.com');
    });

    it('accepts a tag after a plus sign and a subdomain', function (): void {
        expect(Email::fromString('anna+news@mail.example.com')->value()->toString())->toBe('anna+news@mail.example.com');
    });

    it('lower-cases and trims the address', function (): void {
        expect(Email::fromString('  Anna.Meier@Example.COM ')->value()->toString())->toBe('anna.meier@example.com');
    });

    it('builds the same value through the constructor and the factory', function (): void {
        expect((string) new Email('a@example.com'))->toBe((string) Email::fromString('a@example.com'));
    });

    it('builds an address from its parts', function (): void {
        expect(Email::fromParts('Anna', 'Example.com')->value()->toString())->toBe('anna@example.com');
    });

    it('rejects a malformed address', function (string $input): void {
        expect(fn () => Email::fromString($input))
            ->toThrow(ValidationException::class, 'Invalid email format provided');
    })->with([
        'no at sign' => 'anna.example.com',
        'two at signs' => 'a@b@example.com',
        'empty' => '',
        'blank' => '   ',
        'space inside' => 'an na@example.com',
        'display name' => 'Anna <a@example.com>',
        'leading dot' => '.a@example.com',
        'double dot' => 'a..b@example.com',
        'trailing dot in the domain' => 'a@example.com.',
        'domain starting with a hyphen' => 'a@-example.com',
    ]);

    it('rejects empty parts', function (): void {
        expect(fn () => Email::fromParts('', 'example.com'))->toThrow(ValidationException::class);
    });
});

describe('parts', function (): void {
    it('splits the address', function (): void {
        $email = Email::fromString('Anna.Meier+x@Mail.Example.com');

        expect($email->localPart()->toString())->toBe('anna.meier+x')
            ->and($email->domain()->toString())->toBe('mail.example.com')
            ->and($email->topLevelDomain()->toString())->toBe('com');
    });

    it('reduces the domain to its last two labels', function (string $address, string $expected): void {
        expect(Email::fromString($address)->domainWithoutSubdomain()->toString())->toBe($expected);
    })->with([
        'no subdomain' => ['a@example.com', 'example.com'],
        'one subdomain' => ['a@mail.example.com', 'example.com'],
        'two subdomains' => ['a@x.y.example.com', 'example.com'],
    ]);

    it('does not let the returned string change the address', function (): void {
        $email = Email::fromString('a@example.com');

        $email->value()->append('x');
        $email->localPart()->append('x');

        expect($email->value()->toString())->toBe('a@example.com');
    });
});

describe('comparison', function (): void {
    it('is equal when the normalised addresses match', function (): void {
        expect(Email::fromString('A@Example.com')->equals(Email::fromString('a@example.com')))->toBeTrue();
    });

    it('is not equal for a different address', function (): void {
        expect(Email::fromString('a@example.com')->equals(Email::fromString('b@example.com')))->toBeFalse();
    });

    it('compares the domain exactly', function (): void {
        $email = Email::fromString('a@example.com');

        expect($email->isSameDomain(Email::fromString('b@example.com')))->toBeTrue()
            ->and($email->isSameDomain(Email::fromString('b@mail.example.com')))->toBeFalse();
    });

    it('compares the main domain regardless of the subdomain', function (): void {
        expect(Email::fromString('a@mail.example.com')->isSameMainDomain(Email::fromString('b@example.com')))->toBeTrue()
            ->and(Email::fromString('a@example.com')->isSameMainDomain(Email::fromString('b@example.org')))->toBeFalse();
    });
});

describe('providers', function (): void {
    it('treats an address at a well known mail provider as personal', function (): void {
        expect(Email::fromString('a@gmail.com')->isCompanyEmail())->toBeFalse()
            ->and(Email::fromString('a@mail.gmail.com')->isCompanyEmail())->toBeFalse()
            ->and(Email::fromString('a@web.de')->isCompanyEmail())->toBeFalse();
    });

    it('treats any other domain as a company', function (): void {
        expect(Email::fromString('a@firma.de')->isCompanyEmail())->toBeTrue();
    });

    it('recognises a german provider', function (): void {
        expect(Email::fromString('a@web.de')->isGermanProvider())->toBeTrue()
            ->and(Email::fromString('a@gmx.net')->isGermanProvider())->toBeTrue()
            ->and(Email::fromString('a@gmail.com')->isGermanProvider())->toBeFalse()
            ->and(Email::fromString('a@firma.de')->isGermanProvider())->toBeFalse();
    });
});

describe('provider lists', function (): void {
    it('treats every german provider as personal', function (string $domain): void {
        $email = Email::fromString('a@'.$domain);

        expect($email->isGermanProvider())->toBeTrue()
            ->and($email->isCompanyEmail())->toBeFalse();
    })->with(['web.de', 'gmx.de', 'gmx.net', 't-online.de', 'freenet.de', 'arcor.de', '1und1.de', 'posteo.de', 'mailbox.org']);

    it('treats the international providers as personal and not as german', function (string $domain): void {
        $email = Email::fromString('a@'.$domain);

        expect($email->isCompanyEmail())->toBeFalse()
            ->and($email->isGermanProvider())->toBeFalse();
    })->with(['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com', 'icloud.com', 'me.com', 'mac.com']);
});

describe('derived values', function (): void {
    it('hides the local part but keeps its first and last character', function (string $local, string $expected): void {
        expect(Email::fromString($local.'@example.com')->obfuscate()->toString())->toBe($expected);
    })->with([
        'one character' => ['a', '*@example.com'],
        'two characters' => ['ab', '**@example.com'],
        'three characters' => ['abc', 'a*c@example.com'],
        'four characters' => ['abcd', 'a**d@example.com'],
        'ten characters' => ['anna.meier', 'a********r@example.com'],
    ]);

    it('turns the local part into a display name', function (): void {
        expect(Email::fromString('anna.meier_x-y+z@example.com')->displayName()->toString())->toBe('Anna Meier X Y Z');
    });

    it('turns the local part into a username', function (): void {
        expect(Email::fromString('Anna.Meier_x-y+z@example.com')->generateUsername()->toString())->toBe('annameierxyz');
    });

    it('keeps digits in the username', function (): void {
        expect(Email::fromString('anna.meier2@example.com')->generateUsername()->toString())->toBe('annameier2');
    });

    it('is valid for notifications unless the local part marks a sender that does not read', function (): void {
        expect(Email::fromString('anna@example.com')->isValidForNotifications())->toBeTrue()
            ->and(Email::fromString('donotreply@example.com')->isValidForNotifications())->toBeFalse();
    });
});

describe('representation', function (): void {
    it('casts to the normalised address', function (): void {
        $email = Email::fromString('A@Example.com');

        expect((string) $email)->toBe('a@example.com')
            ->and($email->toString())->toBe('a@example.com');
    });

    it('serialises to the address', function (): void {
        expect(Email::fromString('a@example.com')->jsonSerialize())->toBe('a@example.com')
            ->and(json_encode(['email' => Email::fromString('a@example.com')]))->toBe('{"email":"a@example.com"}');
    });

    it('exposes its parts and flags as an array', function (): void {
        expect(json_encode(Email::fromString('Anna@Mail.Example.com')->toArray()))->toBe(
            '{"email":"anna@mail.example.com","local_part":"anna","domain":"mail.example.com","top_level_domain":"com","is_company_email":true,"is_german_provider":false,"is_valid_for_notifications":true}',
        );
    });
});

// Known defect found while characterising Email. It describes the behaviour we
// want, not the one we have; turn it into a real test when it is fixed.
describe('known defects', function (): void {
    it('finds the registrable domain below a public suffix', function (): void {
        expect(Email::fromString('a@mail.example.co.uk')->domainWithoutSubdomain()->toString())->toBe('example.co.uk');
    })->todo('only the last two labels are kept, so the result is co.uk and every .co.uk address shares a main domain');
});
