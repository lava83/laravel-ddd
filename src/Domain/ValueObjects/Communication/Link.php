<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Communication;

use Illuminate\Support\Stringable;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

class Link extends ValueObject
{
    private Stringable $value;

    private Stringable $scheme;

    private Stringable $host;

    private Stringable $path;

    private Stringable $query;

    /**
     * @throws ValidationException
     */
    final public function __construct(string $link)
    {
        $this->validate($link);
        $this->extractParts($link);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public static function fromString(string $link): static
    {
        return new static($link);
    }

    public function value(): Stringable
    {
        return $this->value;
    }

    public function scheme(): Stringable
    {
        return $this->scheme;
    }

    public function host(): Stringable
    {
        return $this->host;
    }

    public function path(): Stringable
    {
        return $this->path;
    }

    public function query(): Stringable
    {
        return $this->query;
    }

    public function jsonSerialize(): string
    {
        return (string) $this->value;
    }

    /**
     * @throws ValidationException
     */
    private function extractParts(string $link): void
    {
        $parts = parse_url($link);

        if (!isset($parts['scheme'], $parts['host'])) {
            throw new ValidationException('Invalid URL format provided');
        }

        $this->scheme = str($parts['scheme']);
        $this->host = str($parts['host']);
        $this->path = str($parts['path']);
        $this->query = str($parts['query'] ?? '');

        $this->value = $this->scheme
            ->append('://')
            ->append((string) $this->host)
            ->append((string) $this->path)
            ->when($this->query->length() > 0, fn(Stringable $s) => $s->append('?')->append((string) $this->query));
    }

    private function validate(string $url): void
    {
        $validator = validator(['url' => $url], [
            'url' => ['required', 'url'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException('Invalid URL format provided');
        }
    }
}
