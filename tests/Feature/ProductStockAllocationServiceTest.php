<?php

namespace Tests\Feature;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Enums\SaleMethodEnum;
use App\Enums\StockDirectionEnum;
use App\Enums\UomQuantityTypeEnum;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMovement;
use App\Models\ProductMovementAllocation;
use App\Models\Supplier;
use App\Models\UnitOfMeasurement;
use App\Models\UomCategory;
use App\Models\User;
use App\Models\Warehouse;
use App\Service\ExternalProductReorder;
use App\Service\ProductService;
use App\Service\ProductStockAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductStockAllocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        $this->artisan('migrate:fresh');
    }

    private function createProduct(string $saleMethod = SaleMethodEnum::FIFO->value): Product
    {
        $category = ProductCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $uomCategory = UomCategory::query()->create([
            'name' => 'Count-' . uniqid(),
            'description' => 'Test category',
            'quantity_type' => UomQuantityTypeEnum::DECIMAL->value,
        ]);

        $uom = UnitOfMeasurement::query()->create([
            'uom_code' => 'UOM' . random_int(10000, 99999),
            'name' => 'Piece-' . uniqid(),
            'symbol' => 'pc',
            'category_id' => $uomCategory->id,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'product_name' => 'Product ' . uniqid(),
            'product_sku_code' => 'PRD-' . strtoupper(substr(md5((string) microtime(true)), 0, 8)),
            'barcode' => null,
            'product_description' => 'Test product',
            'product_type' => ProductTypeEnum::EXTERNAL_PURCHASED->value,
            'sale_method' => $saleMethod,
            'product_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'base_uom_id' => $uom->id,
        ]);
    }

    private function createInMovement(
        Product $product,
        float $quantity,
        float $remaining,
        float $sellingUsd,
        string $movementDate,
        string $movementType = ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value
    ): ProductMovement {
        return ProductMovement::query()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'remaining_quantity' => $remaining,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'direction' => StockDirectionEnum::IN->value,
            'movement_type' => $movementType,
            'is_sold' => $remaining < $quantity,
            'movement_date' => $movementDate,
            'purchase_unit_price_in_usd' => max(0, $sellingUsd - 1),
            'purchase_total_price_in_usd' => max(0, ($sellingUsd - 1) * $quantity),
            'purchase_unit_price_in_riel' => max(0, ($sellingUsd - 1) * 4100),
            'purchase_total_price_in_riel' => max(0, ($sellingUsd - 1) * $quantity * 4100),
            'exchange_rate_from_usd_to_riel' => 4100,
            'exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'selling_unit_price_in_usd' => $sellingUsd,
            'selling_unit_price_in_riel' => $sellingUsd * 4100,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
        ]);
    }

    public function test_fifo_consumes_oldest_stock_first(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(SaleMethodEnum::FIFO->value);

        $first = $this->createInMovement($product, 100, 100, 5, '2026-05-01 10:00:00');
        $second = $this->createInMovement($product, 50, 50, 6, '2026-05-02 10:00:00', ProductStockMovementTypeEnum::RE_ORDER->value);

        $service = app(ProductStockAllocationService::class);
        $result = $service->allocateProductForSale($product, 30, $user->id, '2026-05-10 10:00:00');

        $first->refresh();
        $second->refresh();

        $this->assertEquals(70.0, (float) $first->remaining_quantity);
        $this->assertEquals(50.0, (float) $second->remaining_quantity);
        $this->assertCount(1, $result['allocations']);
        $this->assertEquals($first->id, (int) $result['allocations'][0]->source_movement_id);
        $this->assertEquals(30.0, (float) $result['allocations'][0]->allocated_quantity);
    }

    public function test_fifo_consumes_multiple_lots_and_calculates_total(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(SaleMethodEnum::FIFO->value);

        $first = $this->createInMovement($product, 20, 20, 5, '2026-05-01 10:00:00');
        $second = $this->createInMovement($product, 50, 50, 6, '2026-05-02 10:00:00', ProductStockMovementTypeEnum::RE_ORDER->value);

        $service = app(ProductStockAllocationService::class);
        $result = $service->allocateProductForSale($product, 30, $user->id, '2026-05-10 10:00:00');

        $first->refresh();
        $second->refresh();

        $this->assertEquals(0.0, (float) $first->remaining_quantity);
        $this->assertEquals(40.0, (float) $second->remaining_quantity);
        $this->assertCount(2, $result['allocations']);

        $summary = $result['allocation_summary'];
        $this->assertEquals(160.0, (float) $summary['total_amount_usd']);

        $allocA = collect($summary['lots'])->firstWhere('source_movement_id', $first->id);
        $allocB = collect($summary['lots'])->firstWhere('source_movement_id', $second->id);

        $this->assertEquals(20.0, (float) ($allocA['allocated_quantity'] ?? 0));
        $this->assertEquals(10.0, (float) ($allocB['allocated_quantity'] ?? 0));
    }

    public function test_lifo_consumes_newest_stock_first(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(SaleMethodEnum::LIFO->value);

        $older = $this->createInMovement($product, 100, 100, 5, '2026-05-01 10:00:00');
        $newer = $this->createInMovement($product, 50, 50, 6, '2026-05-02 10:00:00', ProductStockMovementTypeEnum::RE_ORDER->value);

        $service = app(ProductStockAllocationService::class);
        $result = $service->allocateProductForSale($product, 30, $user->id, '2026-05-10 10:00:00');

        $older->refresh();
        $newer->refresh();

        $this->assertEquals(100.0, (float) $older->remaining_quantity);
        $this->assertEquals(20.0, (float) $newer->remaining_quantity);
        $this->assertCount(1, $result['allocations']);
        $this->assertEquals($newer->id, (int) $result['allocations'][0]->source_movement_id);
    }

    public function test_insufficient_stock_fails_without_side_effects(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(SaleMethodEnum::FIFO->value);

        $movement = $this->createInMovement($product, 20, 20, 5, '2026-05-01 10:00:00');

        $service = app(ProductStockAllocationService::class);

        try {
            $service->allocateProductForSale($product, 25, $user->id, '2026-05-10 10:00:00');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $movement->refresh();
        $this->assertEquals(20.0, (float) $movement->remaining_quantity);
        $this->assertDatabaseCount('product_movement_allocations', 0);

        $outCount = ProductMovement::query()
            ->where('product_id', $product->id)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->count();
        $this->assertEquals(0, $outCount);
    }

    public function test_product_detail_returns_stock_lots_with_status(): void
    {
        $product = $this->createProduct(SaleMethodEnum::FIFO->value);

        $full = $this->createInMovement($product, 100, 100, 5, '2026-05-01 10:00:00');
        $partial = $this->createInMovement($product, 50, 20, 6, '2026-05-02 10:00:00', ProductStockMovementTypeEnum::RE_ORDER->value);
        $consumed = $this->createInMovement($product, 30, 0, 7, '2026-05-03 10:00:00', ProductStockMovementTypeEnum::RE_ORDER->value);

        ProductMovementAllocation::query()->create([
            'sale_movement_id' => ProductMovement::query()->create([
                'product_id' => $product->id,
                'quantity' => 30,
                'remaining_quantity' => 0,
                'product_status' => ProductStatusEnum::COMPLETED->value,
                'direction' => StockDirectionEnum::OUT->value,
                'movement_type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
                'is_sold' => true,
                'movement_date' => '2026-05-06 10:00:00',
            ])->id,
            'source_movement_id' => $partial->id,
            'allocated_quantity' => 30,
            'selling_unit_price_in_usd' => 6,
            'selling_unit_price_in_riel' => 24600,
            'cost_unit_price_in_usd' => 4,
            'cost_unit_price_in_riel' => 16400,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'cost_exchange_rate_from_usd_to_riel' => 4100,
            'cost_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
        ]);

        $service = app(ProductService::class);
        $response = $service->getProductDetail($product->id);
        $payload = $response->getData(true);

        $this->assertTrue((bool) ($payload['status'] ?? false));
        $data = $payload['data'] ?? [];

        $this->assertEquals(120.0, (float) ($data['current_qty_in_stock'] ?? 0));
        $this->assertEquals(120.0, (float) ($data['available_qty_in_stock'] ?? 0));

        $lots = collect($data['stock_lots'] ?? []);
        $this->assertCount(3, $lots);

        $fullLot = $lots->firstWhere('id', $full->id);
        $partialLot = $lots->firstWhere('id', $partial->id);
        $consumedLot = $lots->firstWhere('id', $consumed->id);

        $this->assertEquals('AVAILABLE', $fullLot['lot_status']);
        $this->assertEquals('PARTIALLY_CONSUMED', $partialLot['lot_status']);
        $this->assertEquals('CONSUMED', $consumedLot['lot_status']);
    }

    public function test_updating_allocated_reorder_lot_is_blocked(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(SaleMethodEnum::FIFO->value);

        $source = $this->createInMovement(
            $product,
            100,
            90,
            6,
            '2026-05-01 10:00:00',
            ProductStockMovementTypeEnum::RE_ORDER->value,
        );

        $saleMovement = ProductMovement::query()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'remaining_quantity' => 0,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'direction' => StockDirectionEnum::OUT->value,
            'movement_type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
            'is_sold' => true,
            'movement_date' => '2026-05-07 10:00:00',
            'created_by' => $user->id,
            'last_updated_by' => $user->id,
        ]);

        ProductMovementAllocation::query()->create([
            'sale_movement_id' => $saleMovement->id,
            'source_movement_id' => $source->id,
            'allocated_quantity' => 10,
            'selling_unit_price_in_usd' => 6,
            'selling_unit_price_in_riel' => 24600,
            'cost_unit_price_in_usd' => 5,
            'cost_unit_price_in_riel' => 20500,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'cost_exchange_rate_from_usd_to_riel' => 4100,
            'cost_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);
        $service = app(ExternalProductReorder::class);
        $request = Request::create('/api/products/' . $product->id . '/reorder/external-purchase/' . $source->id, 'PATCH', [
            'quantity' => 120,
            'movement_date' => '2026-05-08 10:00:00',
            'purchase_unit_price_in_usd' => 5,
            'exchange_rate_from_usd_to_riel' => 4100,
            'selling_unit_price_in_usd' => 6,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'note' => 'Try update allocated lot',
        ]);

        $response = $service->updateReorderExternalPurchasedProduct($request, $product->id, $source->id);
        $payload = $response->getData(true);

        $this->assertFalse((bool) ($payload['status'] ?? true));
        $errors = $payload['errors'] ?? [];
        $errorText = is_array($errors) ? json_encode($errors) : (string) $errors;
        $messageText = (string) ($payload['message'] ?? '');
        $responseText = trim($messageText . ' ' . (string) $errorText);

        $this->assertTrue(
            str_contains($responseText, 'already been used in a sale')
            || str_contains($responseText, 'Cannot update allocated stock lot')
        );
    }

    public function test_allocation_prevents_oversell_on_second_attempt(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct(SaleMethodEnum::FIFO->value);
        $this->createInMovement($product, 20, 20, 5, '2026-05-01 10:00:00');

        $service = app(ProductStockAllocationService::class);

        DB::transaction(function () use ($service, $product, $user) {
            $service->allocateProductForSale($product, 15, $user->id, '2026-05-10 10:00:00');
        });

        $this->expectException(ValidationException::class);
        $service->allocateProductForSale($product, 10, $user->id, '2026-05-10 11:00:00');
    }
}
