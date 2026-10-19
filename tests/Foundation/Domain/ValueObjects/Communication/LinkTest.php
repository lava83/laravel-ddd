<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Link;

describe('construction', function (): void {
    it('splits a url into its parts', function (): void {
        $link = Link::fromString('https://example.com/a/b');

        expect($link->scheme()->toString())->toBe('https')
            ->and($link->host()->toString())->toBe('example.com')
            ->and($link->path()->toString())->toBe('/a/b')
            ->and($link->query()->toString())->toBe('')
            ->and($link->value()->toString())->toBe('https://example.com/a/b');
    });

    it('builds the same value through the constructor and the factory', function (): void {
        expect((string) new Link('https://example.com/a'))->toBe((string) Link::fromString('https://example.com/a'));
    });

    it('keeps the query string', function (): void {
        $link = Link::fromString('https://example.com/a?x=1&y=2');

        expect($link->query()->toString())->toBe('x=1&y=2')
            ->and($link->value()->toString())->toBe('https://example.com/a?x=1&y=2');
    });

    it('accepts a root path', function (): void {
        $link = Link::fromString('https://example.com/');

        expect($link->path()->toString())->toBe('/')
            ->and($link->value()->toString())->toBe('https://example.com/');
    });

    it('accepts http', function (): void {
        expect(Link::fromString('http://example.com/a')->scheme()->toString())->toBe('http');
    });
});

describe('rejection', function (): void {
    it('rejects a string without a scheme', function (): void {
        expect(fn () => Link::fromString('example.com/a'))
            ->toThrow(ValidationException::class, 'Invalid URL format provided');
    });

    it('rejects a javascript url', function (): void {
        expect(fn () => Link::fromString('javascript:alert(1)'))
            ->toThrow(ValidationException::class, 'Invalid URL format provided');
    });

    it('rejects an empty string', function (): void {
        expect(fn () => Link::fromString(''))
            ->toThrow(ValidationException::class, 'Invalid URL format provided');
    });

    it('rejects a url with surrounding whitespace', function (): void {
        expect(fn () => Link::fromString('  https://example.com/a '))
            ->toThrow(ValidationException::class, 'Invalid URL format provided');
    });
});

describe('representation', function (): void {
    it('casts to its value', function (): void {
        expect((string) Link::fromString('https://example.com/a'))->toBe('https://example.com/a');
    });

    it('serialises to its value', function (): void {
        $link = Link::fromString('https://example.com/a?x=1');

        expect($link->jsonSerialize())->toBe('https://example.com/a?x=1')
            ->and(json_encode(['url' => $link]))->toBe('{"url":"https:\/\/example.com\/a?x=1"}');
    });
});

describe('url parts', function (): void {
    it('accepts a url without a path', function (): void {
        expect(Link::fromString('https://example.com')->host()->toString())->toBe('example.com');
    });

    it('keeps the port of the url', function (): void {
        expect(Link::fromString('https://example.com:8080/a')->value()->toString())->toBe('https://example.com:8080/a');
    });

    it('keeps the userinfo of the url', function (): void {
        expect(Link::fromString('https://user:pw@example.com/a')->value()->toString())->toBe('https://user:pw@example.com/a');
    });
});
