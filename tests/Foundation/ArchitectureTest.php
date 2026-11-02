<?php

declare(strict_types=1);

use Lava83\LaravelDdd\Domain\ValueObjects\Business\Money;
use Lava83\LaravelDdd\Domain\ValueObjects\Communication\Email;
use Lava83\LaravelDdd\Domain\ValueObjects\Data\Sqid;
use Lava83\LaravelDdd\Domain\ValueObjects\ValueObject;

describe('Domain purity', function (): void {
    it('never uses a mutable Carbon', function (): void {
        expect('Lava83\LaravelDdd\Domain')
            ->not->toUse([
                'Illuminate\Support\Carbon',
                'Carbon\Carbon',
            ]);
    });

    it('keeps the read models free of Infrastructure', function (): void {
        expect('Lava83\LaravelDdd\Domain\ReadModels')
            ->not->toUse('Lava83\LaravelDdd\Infrastructure');
    });
});

describe('Value objects', function (): void {
    /**
     * @return list<class-string<ValueObject>>
     */
    $valueObjects = function (): array {
        $classes = [];
        $root = dirname(__DIR__, 2).'/src/Domain/ValueObjects';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            $class = 'Lava83\LaravelDdd\Domain\ValueObjects\\'
                .str_replace(['/', '.php'], ['\\', ''], substr((string) $file, strlen($root) + 1));

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isSubclassOf(ValueObject::class) && ! $reflection->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    };

    it('finds the value objects of the package', function () use ($valueObjects): void {
        expect($valueObjects())->toContain(Email::class, Money::class, Sqid::class);
    });

    it('validates in the constructor of every concrete value object', function () use ($valueObjects): void {
        foreach ($valueObjects() as $class) {
            $constructor = (new ReflectionClass($class))->getConstructor();
            $source = array_slice(
                file((string) $constructor?->getFileName()) ?: [],
                (int) $constructor?->getStartLine() - 1,
                (int) $constructor?->getEndLine() - (int) $constructor?->getStartLine() + 1,
            );

            expect(implode('', $source))->toContain('parent::__construct(');
        }
    });

    it('keeps validate() out of the public api', function () use ($valueObjects): void {
        foreach ($valueObjects() as $class) {
            expect((new ReflectionMethod($class, 'validate'))->isPublic())->toBeFalse();
        }
    });
});
