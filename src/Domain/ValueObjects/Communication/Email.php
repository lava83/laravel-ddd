<?php

declare(strict_types=1);

namespace Lava83\LaravelDdd\Domain\ValueObjects\Communication;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Stringable;
use JsonSerializable;
use Lava83\LaravelDdd\Domain\Exceptions\ValidationException;

class Email implements JsonSerializable, \Stringable
{
    private readonly Stringable $value;

    private Stringable $localPart;

    private Stringable $domain;

    public function __construct(string $email)
    {
        $email = str($email);

        $this->validate($email);
        $this->value = $email->lower()->trim();
        $this->parseEmailParts();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function fromString(string $email): static
    {
        return new static($email);
    }

    public static function fromParts(string $localPart, string $domain): static
    {
        return new static($localPart.'@'.$domain);
    }

    public function value(): Stringable
    {
        return $this->value;
    }

    public function localPart(): Stringable
    {
        return $this->localPart;
    }

    public function domain(): Stringable
    {
        return $this->domain;
    }

    public function domainWithoutSubdomain(): Stringable
    {
        $domainParts = $this->domain->explode('.');

        return str($domainParts->slice(-2)->implode('.'));
    }

    public function topLevelDomain(): Stringable
    {
        return $this->domain->afterLast('.');
    }

    public function equals(Email $other): bool
    {
        return $this->value->exactly($other->value);
    }

    public function isSameDomain(Email $other): bool
    {
        return $this->domain->exactly($other->domain);
    }

    public function isSameMainDomain(Email $other): bool
    {
        return $this->domainWithoutSubdomain()->exactly($other->domainWithoutSubdomain());
    }

    public function isCompanyEmail(): bool
    {
        // Common personal email providers
        $personalProviders = [
            'gmail.com',
            'yahoo.com',
            'hotmail.com',
            'outlook.com',
            'web.de',
            'gmx.de',
            'gmx.net',
            't-online.de',
            'freenet.de',
            'arcor.de',
            'aol.com',
            'icloud.com',
            'me.com',
            'mac.com',
        ];

        return ! in_array($this->domainWithoutSubdomain(), $personalProviders);
    }

    public function isGermanProvider(): bool
    {
        $germanProviders = [
            'web.de',
            'gmx.de',
            'gmx.net',
            't-online.de',
            'freenet.de',
            'arcor.de',
            '1und1.de',
            'posteo.de',
            'mailbox.org',
        ];

        return in_array($this->domainWithoutSubdomain(), $germanProviders);
    }

    public function obfuscate(): Stringable
    {
        $localLength = $this->localPart->length();

        if ($localLength <= 2) {
            $obfuscatedLocal = str('*')->repeat($localLength);
        } else {
            $obfuscatedLocal = str($this->localPart[0])->append((string) str('*')->repeat($localLength - 2))->append($this->localPart[-1]);
        }

        return str($obfuscatedLocal.'@'.$this->domain);
    }

    public function displayName(): Stringable
    {
        return $this->localPart->replace(['.', '_', '-', '+'], ' ')
            ->title();
    }

    public function encrypt(): Stringable
    {
        return $this->value->encrypt();
    }

    public function generateUsername(): Stringable
    {
        return $this->localPart->replaceMatches('/[^a-zA-Z0-9]/', '')
            ->lower();
    }

    public function isValidForNotifications(): bool
    {
        $blockedPrefixes = ['noreply', 'no-reply', 'donotreply', 'bounce'];

        return ! $this->localPart->startsWith($blockedPrefixes);
    }

    public function domainAge(): ?int
    {
        // This would typically integrate with a WHOIS service
        // For now, return null but structure is ready for implementation
        return null;
    }

    /**
     * @return array<string, bool|Stringable>
     */
    public function toArray(): array
    {
        return [
            'email' => $this->value,
            'local_part' => $this->localPart,
            'domain' => $this->domain,
            'top_level_domain' => $this->topLevelDomain(),
            'is_company_email' => $this->isCompanyEmail(),
            'is_german_provider' => $this->isGermanProvider(),
            'is_valid_for_notifications' => $this->isValidForNotifications(),
        ];
    }

    public function jsonSerialize(): string
    {
        return (string) $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Create a mailto link
     */
    public function toMailtoLink(string $subject = '', string $body = ''): string
    {
        $params = [];

        if (filled($subject)) {
            $params[] = 'subject='.urlencode($subject);
        }

        if (filled($body)) {
            $params[] = 'body='.urlencode($body);
        }

        $queryString = filled($params) ? '?'.implode('&', $params) : '';

        return 'mailto:'.$this->value.$queryString;
    }

    /**
     * Get Gravatar URL for this email
     */
    public function gravatarUrl(int $size = 80, string $default = 'identicon'): string
    {
        $hash = md5((string) $this->value->lower()->trim());

        return sprintf('https://www.gravatar.com/avatar/%s?s=%d&d=%s', $hash, $size, $default);
    }

    /**
     * Validate email with external service (placeholder for future implementation)
     */
    public function validateWithExternalService(): bool
    {
        // Placeholder for integration with email validation services
        // like ZeroBounce, EmailValidation, etc.
        return true;
    }

    /**
     * Check if email is likely a role-based email
     */
    public function isRoleBased(): bool
    {
        $roleBasedPrefixes = [
            'admin',
            'administrator',
            'info',
            'contact',
            'support',
            'help',
            'sales',
            'marketing',
            'hr',
            'finance',
            'accounting',
            'billing',
            'legal',
            'compliance',
            'security',
            'it',
            'tech',
            'webmaster',
            'postmaster',
            'abuse',
            'noreply',
            'no-reply',
            'donotreply',
        ];

        return $this->localPart->startsWith($roleBasedPrefixes);
    }

    /**
     * Get email score for HR purposes (higher is better)
     */
    public function hRScore(): int
    {
        $score = 100;

        // Deduct points for personal email providers
        if (! $this->isCompanyEmail()) {
            $score -= 20;
        }

        // Deduct points for role-based emails
        if ($this->isRoleBased()) {
            $score -= 30;
        }

        // Deduct points if not valid for notifications
        if (! $this->isValidForNotifications()) {
            $score -= 50;
        }

        // Add points for German providers (local compliance)
        if ($this->isGermanProvider()) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }

    private function validate(Stringable $email): void
    {
        $email = $email->trim();

        $validator = Validator::make(
            ['email' => $email],
            [
                'email' => [
                    'required',
                    'email:rfc',
                    fn (string $attribute, string $value, Closure $fail) => $this->validateBusiness(str($value), $fail),
                ],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException('Invalid email format provided');
        }
    }

    private function validateBusiness(Stringable $email, Closure $fail): void
    {
        $localPart = $email->before('@');
        $domain = $email->after('@');

        // Block obviously fake/temporary email services (extend as needed)
        $blockedDomains = [
            '10minutemail.com',
            'tempmail.org',
            'guerrillamail.com',
            'mailinator.com',
            'temp-mail.org',
            'throwaway.email',
        ];

        if (in_array($domain, $blockedDomains)) {
            $fail('Temporary email addresses are not allowed');
            // throw new ValidationException('Temporary email addresses are not allowed');
        }

        // Block emails with suspicious patterns
        $suspiciousPatterns = [
            '/test.*test/i',
            '/fake.*fake/i',
            '/spam.*spam/i',
            '/noreply/i',
            '/no-reply/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, (string) $localPart)) {
                $fail('Email address appears to be invalid or test address');
            }
        }
    }

    private function parseEmailParts(): void
    {
        $this->localPart = $this->value->before('@');
        $this->domain = $this->value->after('@');
    }
}
