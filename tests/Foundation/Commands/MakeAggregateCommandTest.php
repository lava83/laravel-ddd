<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function tmpDddPath(string $relative): string
{
    return base_path('TmpDddTest/'.$relative);
}

beforeEach(function (): void {
    config()->set('laravel-ddd.bounded_contexts_root_namespace', 'TmpDddTest');
    config()->set('laravel-ddd.bounded_contexts_without_own_layers', true);

    File::deleteDirectory(base_path('TmpDddTest'));
});

afterEach(function (): void {
    File::deleteDirectory(base_path('TmpDddTest'));
});

describe('make:aggregate', function (): void {
    it('scaffolds a uuid aggregate with repository and mapper', function (): void {
        $this->artisan('make:aggregate', [
            'name' => 'product',
            'bounded-context' => 'shop',
            '--with-repository' => true,
            '--with-entity-mapper' => true,
            '--id-type' => 'uuid',
        ])->assertSuccessful();

        expect(File::exists(tmpDddPath('Domain/Shop/Aggregates/Product.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Domain/Shop/ValueObjects/Identity/ProductId.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Infrastructure/Shop/Models/ProductModel.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Domain/Shop/Contracts/ProductRepositoryContract.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Infrastructure/Shop/Repositories/EloquentProductRepository.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Infrastructure/Shop/Mappers/ProductMapper.php')))->toBeTrue();

        expect(File::get(tmpDddPath('Domain/Shop/Aggregates/Product.php')))
            ->toContain('namespace TmpDddTest\Domain\Shop\Aggregates;')
            ->toContain('class Product extends Aggregate')
            ->toContain('protected readonly ProductId $id')
            ->toContain('ProductId::generate()')
            ->toContain('ProductId::fromValue($state->getKey())')
            ->toContain('parent::__construct();');

        expect(File::get(tmpDddPath('Domain/Shop/ValueObjects/Identity/ProductId.php')))
            ->toContain('final class ProductId extends Uuid {}');

        expect(File::get(tmpDddPath('Infrastructure/Shop/Models/ProductModel.php')))
            ->toContain('use HasUuids;')
            ->toContain("#[Table('products')]")
            ->toContain('#[Fillable(')
            ->toContain('protected ?string $entityClassName = Product::class;')
            ->not->toContain('protected $table')
            ->not->toContain('protected $fillable');

        // The installed package exposes the two-argument findOrCreateModelFillData().
        expect(File::get(tmpDddPath('Infrastructure/Shop/Mappers/ProductMapper.php')))
            ->toContain('self::findOrCreateModelFillData($entity, $data)')
            ->not->toContain('ProductModel::class, $data');

        expect(File::get(tmpDddPath('Infrastructure/Shop/Repositories/EloquentProductRepository.php')))
            ->toContain('implements ProductRepositoryContract')
            ->not->toContain('public function __construct() {}');
    });

    it('scaffolds an integer aggregate without repository or mapper', function (): void {
        $this->artisan('make:aggregate', [
            'name' => 'Order',
            'bounded-context' => 'Sales',
            '--id-type' => 'integer',
            '--no-interaction' => true,
        ])->assertSuccessful();

        expect(File::exists(tmpDddPath('Domain/Sales/Aggregates/Order.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Infrastructure/Sales/Models/OrderModel.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Domain/Sales/Contracts/OrderRepositoryContract.php')))->toBeFalse()
            ->and(File::exists(tmpDddPath('Infrastructure/Sales/Mappers/OrderMapper.php')))->toBeFalse();

        expect(File::get(tmpDddPath('Domain/Sales/Aggregates/Order.php')))
            ->toContain('protected OrderId $id')
            ->not->toContain('protected readonly OrderId $id')
            ->toContain('OrderId::new()')
            ->toContain('OrderId::fromInt((int) $state->getKey())');

        expect(File::get(tmpDddPath('Domain/Sales/ValueObjects/Identity/OrderId.php')))
            ->toContain('final class OrderId extends Integer {}');

        expect(File::get(tmpDddPath('Infrastructure/Sales/Models/OrderModel.php')))
            ->not->toContain('HasUuids');
    });

    it('places layers inside the bounded context when contexts own their layers', function (): void {
        config()->set('laravel-ddd.bounded_contexts_without_own_layers', false);

        $this->artisan('make:aggregate', [
            'name' => 'Invoice',
            'bounded-context' => 'Billing',
            '--no-interaction' => true,
        ])->assertSuccessful();

        expect(File::exists(tmpDddPath('Billing/Domain/Aggregates/Invoice.php')))->toBeTrue()
            ->and(File::exists(tmpDddPath('Billing/Infrastructure/Models/InvoiceModel.php')))->toBeTrue();

        expect(File::get(tmpDddPath('Billing/Domain/Aggregates/Invoice.php')))
            ->toContain('namespace TmpDddTest\Billing\Domain\Aggregates;');
    });

    it('does not overwrite an existing file without --force', function (): void {
        File::ensureDirectoryExists(dirname(tmpDddPath('Domain/Shop/Aggregates/Product.php')));
        File::put(tmpDddPath('Domain/Shop/Aggregates/Product.php'), '<?php // sentinel');

        $this->artisan('make:aggregate', [
            'name' => 'Product',
            'bounded-context' => 'Shop',
            '--no-interaction' => true,
        ])->assertSuccessful();

        expect(File::get(tmpDddPath('Domain/Shop/Aggregates/Product.php')))->toContain('// sentinel');
    });

    it('rejects an invalid id type', function (): void {
        $this->artisan('make:aggregate', [
            'name' => 'Broken',
            'bounded-context' => 'Nope',
            '--id-type' => 'guid',
            '--no-interaction' => true,
        ])->assertFailed();
    });
});
