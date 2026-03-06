<?php

namespace Database\Seeders;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Helpers\GenerateUniqueSKU;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductRawMaterial;
use App\Models\RawMaterial;
use App\Models\RMStockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProductSeeder extends Seeder
{
    // ─────────────────────────────────────────────────────────────────────────
    // Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /** Number of externally purchased products to seed. */
    private const EXTERNAL_PURCHASE_COUNT = 20;

    /** Number of internally manufactured products to seed. */
    private const INTERNAL_MANUFACTURING_COUNT = 10;

    /** Max BOM items per internally manufactured product. */
    private const MAX_BOM_ITEMS = 3;

    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        $faker = fake();

        $users        = User::all();
        $rawMaterials = RawMaterial::all();

        // ── External Purchase ────────────────────────────────────────────────

        for ($i = 0; $i < self::EXTERNAL_PURCHASE_COUNT; $i++) {
            $userId       = $users->random()->id;
            $movementDate = Carbon::now()->subDays($faker->numberBetween(1, 365));

            $product = $this->createProductRecord($faker, 'EXTERNAL');

            $this->createExternalPurchaseMovement($faker, $product, $userId, $movementDate);
        }

        // ── Internal Manufacturing ───────────────────────────────────────────
        for ($i = 0; $i < self::INTERNAL_MANUFACTURING_COUNT; $i++) {
            $userId       = $users->random()->id;
            $movementDate = Carbon::now()->subDays($faker->numberBetween(1, 180))->toDateTimeString();

            $product = $this->createProductRecord($faker, 'INTERNAL');

            // BOM: pick 1–MAX_BOM_ITEMS raw materials that have sufficient stock
            $bomItems = $this->buildBomItems($faker, $rawMaterials);

            if (empty($bomItems)) {
                // Skip if no raw materials have available stock
                $product->forceDelete();
                continue;
            }

            // Persist BOM pivot records
            foreach ($bomItems as $item) {
                ProductRawMaterial::create([
                    'product_id'      => $product->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity'        => $item['quantity'],
                ]);
            }

            // Create internal manufacturing movement (purchase price = 0)
            $productStatus = $faker->randomElement([
                ProductStatusEnum::DRAFT->value,
                ProductStatusEnum::WORK_IN_PROGRESS->value,
                ProductStatusEnum::PARTIALLY_COMPLETED->value,
                ProductStatusEnum::COMPLETED->value,
                ProductStatusEnum::BLOCKED->value,
            ]);

            $this->createInternalManufacturingMovement(
                $faker,
                $product,
                $userId,
                $movementDate,
                $productStatus
            );

            // Deduct raw material stock respecting FIFO / LIFO
            $this->deductRawMaterialStock($bomItems, $product->id, $userId, $movementDate);
        }

    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create Product record
    // Mirrors ProductService::createProductRecord():
    //   - Auto-generate SKU using format PRD-{CATEGORY}-{RANDOM}
    //   - Requires: product_category_id, uom_id, supplier_id, warehouse_id
    // ─────────────────────────────────────────────────────────────────────────

    private function createProductRecord(\Faker\Generator $faker, string $type): Product
    {
        // Resolve FK IDs straight from the DB (same tables validated by exists: rules)
        $categoryId  = \App\Models\ProductCategory::inRandomOrder()->first()->id;
        $uomId       = \App\Models\UOM::inRandomOrder()->first()->id;
        $supplierId  = \App\Models\Supplier::inRandomOrder()->first()->id;
        $warehouseId = \App\Models\Warehouse::inRandomOrder()->first()->id;

        // Build the same SKU format the API uses: PRD-{CATEGORY}-{RANDOM}
        $product    = new Product();
        $category   = \App\Models\ProductCategory::find($categoryId);
        $product->category()->associate($category);

        $sku = GenerateUniqueSKU::generate(
            model:        $product,
            field:        'product_sku_code',
            randomLength: 6,
            prefix:       'PRD',
            relations:    ['cat' => 'category.category_name'],
            format:       '{prefix}-{cat}-{random}',
        );

        // Prefix makes the product name readable in seed data
        $namePrefix = $type === 'EXTERNAL' ? 'Purchased' : 'Manufactured';

        return Product::create([
            'product_name'        => $namePrefix . ' ' . $faker->words(2, true),
            'product_sku_code'    => $sku,
            'barcode'             => $faker->optional(0.6)->ean13(),
            'product_description' => $faker->optional(0.5)->sentence(),
            'product_category_id' => $categoryId,
            'uom_id'              => $uomId,
            'supplier_id'         => $supplierId,
            'warehouse_id'        => $warehouseId,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // External Purchase movement
    //
    // Rules mirrored from:
    //   - ProductMovementService::createExternalPurchaseMovement()
    //   - CurrencyPricingHelper::fillProductPurchasingCurrencyFields()
    //
    // - direction          = IN
    // - movement_type      = EXTERNAL_PURCHASED
    // - product_type       = EXTERNAL_PURCHASED
    // - product_status     = COMPLETED  (hardcoded — not user-input)
    // - purchase prices    = derived from unit_price × qty + exchange rate
    // - selling prices     = unit only, derived from selling_unit_usd × selling_rate
    // - raw_materials      = NOT allowed / not used
    // ─────────────────────────────────────────────────────────────────────────

    private function createExternalPurchaseMovement(
        \Faker\Generator $faker,
        Product          $product,
        int              $userId,
        Carbon           $movementDate
    ): ProductMovement {
        $quantity             = (float) $faker->randomFloat(4, 1, 500);

        // Purchase pricing
        $purchaseUnitUsd      = (float) $faker->randomFloat(4, 0.5, 200);
        $purchaseTotalUsd     = round($purchaseUnitUsd * $quantity, 4);
        $exchangeUsdToRiel    = (float) $faker->randomFloat(4, 3900, 4300);
        $exchangeRielToUsd    = $exchangeUsdToRiel > 0 ? round(1 / $exchangeUsdToRiel, 8) : 0;
        $purchaseUnitRiel     = round($purchaseUnitUsd * $exchangeUsdToRiel, 0);
        $purchaseTotalRiel    = round($purchaseTotalUsd * $exchangeUsdToRiel, 0);

        // Selling pricing (unit only — no totals per business rule)
        $sellingUnitUsd       = (float) $faker->randomFloat(4, $purchaseUnitUsd, $purchaseUnitUsd * 2);
        $sellingExchangeRate  = (float) $faker->randomFloat(4, 3900, 4300);
        $sellingExchangeInverse = $sellingExchangeRate > 0 ? round(1 / $sellingExchangeRate, 8) : 0;
        $sellingUnitRiel      = round($sellingUnitUsd * $sellingExchangeRate, 0);

        return ProductMovement::create([
            'product_id'                             => $product->id,
            'direction'                              => StockDirectionEnum::IN->value,
            'movement_type'                          => ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
            'product_type'                           => ProductTypeEnum::EXTERNAL_PURCHASED->value,
            'product_status'                         => ProductStatusEnum::COMPLETED->value,
            'quantity'                               => $quantity,
            'is_sold'                                => false,
            'movement_date'                          => $movementDate,
            'note'                                   => $faker->optional(0.4)->sentence(),
            'created_by'                             => $userId,
            'last_updated_by'                        => $userId,

            // Purchase pricing
            'purchase_unit_price_in_usd'             => $purchaseUnitUsd,
            'purchase_total_price_in_usd'            => $purchaseTotalUsd,
            'exchange_rate_from_usd_to_riel'         => $exchangeUsdToRiel,
            'purchase_unit_price_in_riel'            => $purchaseUnitRiel,
            'purchase_total_price_in_riel'           => $purchaseTotalRiel,
            'exchange_rate_from_riel_to_usd'         => $exchangeRielToUsd,

            // Selling pricing (unit only)
            'selling_unit_price_in_usd'              => $sellingUnitUsd,
            'selling_unit_price_in_riel'             => $sellingUnitRiel,
            'selling_exchange_rate_from_usd_to_riel' => $sellingExchangeRate,
            'selling_exchange_rate_from_riel_to_usd' => $sellingExchangeInverse,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal Manufacturing movement
    //
    // Rules mirrored from:
    //   - ProductMovementService::createInternalManufacturingMovement()
    //   - ProductService::forceZeroPurchasePrices()
    //
    // - direction          = IN
    // - movement_type      = INTERNAL_MANUFACTURED
    // - product_type       = INTERNAL_PRODUCED
    // - product_status     = user-chosen (any ProductStatusEnum value)
    // - purchase prices    = all 0 (produced internally, no purchase cost)
    // - selling prices     = unit only, derived from selling_unit_usd × selling_rate
    // ─────────────────────────────────────────────────────────────────────────

    private function createInternalManufacturingMovement(
        \Faker\Generator $faker,
        Product          $product,
        int              $userId,
        string           $movementDate,
        string           $productStatus
    ): ProductMovement {
        $quantity            = (float) $faker->randomFloat(4, 1, 200);

        // Selling pricing (unit only)
        $sellingUnitUsd      = (float) $faker->randomFloat(4, 1, 300);
        $sellingExchangeRate = (float) $faker->randomFloat(4, 3900, 4300);
        $sellingExchangeInverse = $sellingExchangeRate > 0 ? round(1 / $sellingExchangeRate, 8) : 0;
        $sellingUnitRiel     = round($sellingUnitUsd * $sellingExchangeRate, 0);

        return ProductMovement::create([
            'product_id'                             => $product->id,
            'direction'                              => StockDirectionEnum::IN->value,
            'movement_type'                          => ProductStockMovementTypeEnum::INTERNAL_MANUFACTURED->value,
            'product_type'                           => ProductTypeEnum::INTERNAL_PRODUCED->value,
            'product_status'                         => $productStatus,
            'quantity'                               => $quantity,
            'is_sold'                                => false,
            'movement_date'                          => $movementDate,
            'note'                                   => $faker->optional(0.4)->sentence(),
            'created_by'                             => $userId,
            'last_updated_by'                        => $userId,

            // Purchase pricing forced to 0 — produced internally (mirrors forceZeroPurchasePrices)
            'purchase_unit_price_in_usd'             => 0,
            'purchase_total_price_in_usd'            => 0,
            'exchange_rate_from_usd_to_riel'         => 0,
            'purchase_unit_price_in_riel'            => 0,
            'purchase_total_price_in_riel'           => 0,
            'exchange_rate_from_riel_to_usd'         => 0,

            // Selling pricing (unit only)
            'selling_unit_price_in_usd'              => $sellingUnitUsd,
            'selling_unit_price_in_riel'             => $sellingUnitRiel,
            'selling_exchange_rate_from_usd_to_riel' => $sellingExchangeRate,
            'selling_exchange_rate_from_riel_to_usd' => $sellingExchangeInverse,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Build Bill of Materials
    //
    // Mirrors the validation rule: raw_materials[*].raw_material_id must exist
    // in raw_materials table and be distinct. Each item needs sufficient stock.
    // ─────────────────────────────────────────────────────────────────────────

    private function buildBomItems(\Faker\Generator $faker, $rawMaterials): array
    {
        $bomCount   = $faker->numberBetween(1, self::MAX_BOM_ITEMS);
        $candidates = $rawMaterials->shuffle()->take($bomCount * 3);
        $bomItems   = [];
        $usedIds    = [];

        foreach ($candidates as $rm) {
            if (count($bomItems) >= $bomCount) {
                break;
            }

            // Enforce distinct (mirrors 'distinct' rule on raw_materials.*.raw_material_id)
            if (in_array($rm->id, $usedIds, true)) {
                continue;
            }

            // Check available stock (mirrors validateSufficientStock)
            $available = $this->getAvailableStock($rm->id);

            if ($available < 0.0001) {
                continue;
            }

            // Required qty must be ≥ 0.0001 and not exceed available (mirrors min:0.0001 rule)
            $required = (float) $faker->randomFloat(4, 0.0001, min($available * 0.5, 100));

            $bomItems[]  = ['raw_material_id' => $rm->id, 'quantity' => $required];
            $usedIds[]   = $rm->id;
        }

        return $bomItems;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Deduct raw material stock
    //
    // Mirrors RawMaterialStockDeductionService::deductStock():
    //   - Creates PRODUCTION_RECEIPT / OUT movements
    //   - Respects FIFO (asc movement_date) or LIFO (desc movement_date)
    //     based on raw_material.production_method
    //   - Marks fully consumed IN batches with in_used = true
    // ─────────────────────────────────────────────────────────────────────────

    private function deductRawMaterialStock(
        array  $bomItems,
        int    $productId,
        int    $userId,
        string $movementDate
    ): void {
        foreach ($bomItems as $item) {
            $rawMaterialId = (int) $item['raw_material_id'];
            $remaining     = (float) $item['quantity'];

            $rm          = RawMaterial::find($rawMaterialId);
            $inMovements = $this->getOrderedInMovements($rawMaterialId, $rm->production_method);

            foreach ($inMovements as $inMovement) {
                if ($remaining <= 0) {
                    break;
                }

                $batchAvailable = (float) $inMovement->quantity;
                $consume        = min($batchAvailable, $remaining);

                // Create PRODUCTION_RECEIPT OUT record (copies price from source IN batch)
                RMStockMovement::create([
                    'raw_material_id'                => $rawMaterialId,
                    'quantity'                       => $consume,
                    'direction'                      => StockDirectionEnum::OUT->value,
                    'movement_type'                  => RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                    'in_used'                        => false,
                    'movement_date'                  => $movementDate,
                    'unit_price_in_usd'              => $inMovement->unit_price_in_usd,
                    'total_value_in_usd'             => round($inMovement->unit_price_in_usd * $consume, 4),
                    'exchange_rate_from_usd_to_riel' => $inMovement->exchange_rate_from_usd_to_riel,
                    'unit_price_in_riel'             => $inMovement->unit_price_in_riel,
                    'total_value_in_riel'            => round($inMovement->unit_price_in_riel * $consume, 0),
                    'exchange_rate_from_riel_to_usd' => $inMovement->exchange_rate_from_riel_to_usd,
                    'created_by'                     => $userId,
                    'last_updated_by'                => $userId,
                    'note'                           => "Consumed for product ID {$productId}",
                ]);

                // Mark the IN batch fully consumed when entire qty is used
                if ($consume >= $batchAvailable) {
                    $inMovement->update(['in_used' => true]);
                }

                $remaining -= $consume;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers (identical logic to RawMaterialStockDeductionService private methods)
    // ─────────────────────────────────────────────────────────────────────────

    private function getAvailableStock(int $rawMaterialId): float
    {
        $totalIn = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('in_used', false)
            ->sum('quantity');

        $totalOut = RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::OUT->value)
            ->sum('quantity');

        return max(0, (float) $totalIn - (float) $totalOut);
    }

    private function getOrderedInMovements(int $rawMaterialId, mixed $productionMethod)
    {
        $methodValue = is_object($productionMethod) ? $productionMethod->value : (string) $productionMethod;
        $order       = strtoupper($methodValue) === 'LIFO' ? 'desc' : 'asc';

        return RMStockMovement::where('raw_material_id', $rawMaterialId)
            ->where('direction', StockDirectionEnum::IN->value)
            ->where('in_used', false)
            ->orderBy('movement_date', $order)
            ->get();
    }
}
