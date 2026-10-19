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
        $this->value = str($link);

        parent::__construct();

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

        if (! isset($parts['scheme'], $parts['host'])) {
            throw new ValidationException('Invalid URL format provided');
        }

        $this->scheme = str($parts['scheme']);
        $this->host = str($parts['host']);
        $this->path = str($parts['path'] ?? '');
        $this->query = str($parts['query'] ?? '');

        $userinfo = isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $this->value = $this->scheme
            ->append('://', $userinfo, (string) $this->host, $port)
            ->append((string) $this->path);

        if ($this->query->isNotEmpty()) {
            $this->value = $this->value->append('?')->append((string) $this->query);
        }
    }

    protected function validate(): array
    {
        $validator = validator(['value' => (string) $this->value], [
            'value' => ['required', 'url'],
        ]);

        if ($validator->fails()) {
            return ['value' => ['Invalid URL format provided']];
        }

        return [];
    }
}
