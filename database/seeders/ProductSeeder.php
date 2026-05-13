<?php

namespace Database\Seeders;

use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Enums\UomQuantityTypeEnum;
use App\Models\Product;
use App\Models\ProductReorder;
use App\Models\ReorderProductRawMaterial;
use App\Models\ProductMovement;
use App\Models\ProductMovementAllocation;
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

        // Ensure prerequisite data exists when seeder is run in isolation.
        if ($users->isEmpty()) {
            User::factory()->count(5)->create();
            $users = User::all();
        }

        if ($rawMaterials->isEmpty()) {
            // Ensure supporting reference data exists for raw material factories
            $this->call([
                UOMSeeder::class,
                RawMaterialCategorySeeder::class,
                SupplierSeeder::class,
                WarehouseSeeder::class,
            ]);

            RawMaterial::factory()->count(30)->create();
            $rawMaterials = RawMaterial::all();
        }

        // ── External Purchase ────────────────────────────────────────────────

        for ($i = 0; $i < self::EXTERNAL_PURCHASE_COUNT; $i++) {
            $userId       = $users->random()->id;
            $movementDate = Carbon::now()->subDays($faker->numberBetween(1, 365));

            // Create product via factory (external state)
            $product = Product::factory()->external()->create();

            $this->createExternalPurchaseMovement($faker, $product, $userId, $movementDate);
        }

        // ── Internal Manufacturing ───────────────────────────────────────────
        for ($i = 0; $i < self::INTERNAL_MANUFACTURING_COUNT; $i++) {
            $userId       = $users->random()->id;
            $movementDate = Carbon::now()->subDays($faker->numberBetween(1, 180))->toDateTimeString();

            // Create product via factory (internal state)
            $product = Product::factory()->internal()->create();

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
                    'quantity' => $item['quantity'],
                    'quantity_per_unit' => $item['quantity'],
                    'scrap_percentage' => 0,
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

            $movement = $this->createInternalManufacturingMovement(
                $faker,
                $product,
                $userId,
                $movementDate,
                $productStatus
            );

            // Persist a ProductReorder snapshot linked to this product movement
            $productReorder = ProductReorder::create([
                'product_id' => $product->id,
                'product_movement_id' => $movement->id,
                'quantity' => (float) $movement->quantity,
                'status' => 'COMPLETED',
                'is_finalized' => false,
                'created_by' => $userId,
                'last_updated_by' => $userId,
                'notes' => null,
            ]);

            // Persist BOM snapshot rows for this reorder
            foreach ($bomItems as $item) {
                ReorderProductRawMaterial::create([
                    'product_reorder_id' => $productReorder->id,
                    'raw_material_id' => $item['raw_material_id'],
                    'quantity' => $item['quantity'],
                    'quantity_per_unit' => $item['quantity'],
                    'scrap_percentage' => 0,
                ]);
            }

            // Build reference token used to link RM movements to this reorder
            $referenceToken = "REORDER_MOVEMENT_ID:{$movement->id}";

            // Deduct raw material stock respecting FIFO / LIFO, tagging movements with the token
            $this->deductRawMaterialStock($bomItems, $product->id, $userId, $movementDate, $referenceToken);
        }

        $this->seedLotAllocationScenarios($users);

    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create Product record
    // Mirrors ProductService::createProductRecord():
    //   - Auto-generate SKU using format PRD-{CATEGORY}-{RANDOM}
    //   - Requires: product_category_id, base_uom_id, supplier_id, warehouse_id
    // ─────────────────────────────────────────────────────────────────────────

    // Product creation is handled via ProductFactory now.

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
        $quantity             = $this->generateQuantityForProduct($faker, $product, 1, 500);

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
            'product_status'                         => ProductStatusEnum::COMPLETED->value,
            'quantity'                               => $quantity,
            'remaining_quantity'                     => $quantity,
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
    // Internal Production movement
    //
    // Rules mirrored from:
    //   - ProductMovementService::createInternalProductionMovement()
    //   - ProductService::forceZeroPurchasePrices()
    //
    // - direction          = IN
    // - movement_type      = INTERNAL_PRODUCED
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
        $quantity            = $this->generateQuantityForProduct($faker, $product, 1, 200);

        // Selling pricing (unit only)
        $sellingUnitUsd      = (float) $faker->randomFloat(4, 1, 300);
        $sellingExchangeRate = (float) $faker->randomFloat(4, 3900, 4300);
        $sellingExchangeInverse = $sellingExchangeRate > 0 ? round(1 / $sellingExchangeRate, 8) : 0;
        $sellingUnitRiel     = round($sellingUnitUsd * $sellingExchangeRate, 0);

        return ProductMovement::create([
            'product_id'                             => $product->id,
            'direction'                              => StockDirectionEnum::IN->value,
            'movement_type'                          => ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
            'product_status'                         => $productStatus,
            'quantity'                               => $quantity,
            'remaining_quantity'                     => $quantity,
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
            $required = $this->generateQuantityForRawMaterial(
                $faker,
                $rm,
                0.0001,
                min($available * 0.5, 100)
            );

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
        string $movementDate,
        ?string $referenceToken = null
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
                    'note'                           => $this->buildConsumptionNote($productId, $referenceToken),
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

    private function buildConsumptionNote(int $productId, ?string $referenceToken = null): string
    {
        $note = "Consumed for product ID {$productId}";

        if (!empty($referenceToken)) {
            $note .= " | {$referenceToken}";
        }

        return $note;
    }

    private function generateQuantityForProduct(
        \Faker\Generator $faker,
        Product $product,
        float $min,
        float $max
    ): float {
        $product->loadMissing('baseUom.category');
        $isInteger = $this->isIntegerQuantityType($product->baseUom?->category?->quantity_type);

        return $this->generateQuantity($faker, $isInteger, $min, $max);
    }

    private function generateQuantityForRawMaterial(
        \Faker\Generator $faker,
        RawMaterial $rawMaterial,
        float $min,
        float $max
    ): float {
        $rawMaterial->loadMissing('baseUom.category');
        $isInteger = $this->isIntegerQuantityType($rawMaterial->baseUom?->category?->quantity_type);

        return $this->generateQuantity($faker, $isInteger, $min, $max);
    }

    private function isIntegerQuantityType(mixed $quantityType): bool
    {
        if ($quantityType instanceof UomQuantityTypeEnum) {
            return $quantityType === UomQuantityTypeEnum::INTEGER;
        }

        return strtoupper((string) $quantityType) === UomQuantityTypeEnum::INTEGER->value;
    }

    private function generateQuantity(
        \Faker\Generator $faker,
        bool $isInteger,
        float $min,
        float $max
    ): float {
        if ($max < $min) {
            $max = $min;
        }

        if ($isInteger) {
            $intMin = max(1, (int) ceil($min));
            $intMax = max($intMin, (int) floor($max));
            return (float) $faker->numberBetween($intMin, $intMax);
        }

        return (float) $faker->randomFloat(4, $min, $max);
    }

    private function seedLotAllocationScenarios($users): void
    {
        $userId = (int) ($users->first()->id ?? 1);

        // Product A: FIFO with two priced lots and partial consumption from the first lot.
        $productA = Product::factory()->external()->create([
            'product_name' => 'Seed Product A (FIFO Lots)',
            'sale_method' => 'FIFO',
        ]);

        $aLot1 = ProductMovement::create([
            'product_id' => $productA->id,
            'direction' => StockDirectionEnum::IN->value,
            'movement_type' => ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => 1000,
            'remaining_quantity' => 990,
            'is_sold' => true,
            'movement_date' => now()->subDays(10)->toDateTimeString(),
            'purchase_unit_price_in_usd' => 3.5,
            'purchase_total_price_in_usd' => 3500,
            'purchase_unit_price_in_riel' => 14350,
            'purchase_total_price_in_riel' => 14350000,
            'exchange_rate_from_usd_to_riel' => 4100,
            'exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'selling_unit_price_in_usd' => 5,
            'selling_unit_price_in_riel' => 20500,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $userId,
            'last_updated_by' => $userId,
        ]);

        ProductMovement::create([
            'product_id' => $productA->id,
            'direction' => StockDirectionEnum::IN->value,
            'movement_type' => ProductStockMovementTypeEnum::RE_ORDER->value,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'is_sold' => false,
            'movement_date' => now()->subDays(5)->toDateTimeString(),
            'purchase_unit_price_in_usd' => 4.2,
            'purchase_total_price_in_usd' => 420,
            'purchase_unit_price_in_riel' => 17220,
            'purchase_total_price_in_riel' => 1722000,
            'exchange_rate_from_usd_to_riel' => 4100,
            'exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'selling_unit_price_in_usd' => 6,
            'selling_unit_price_in_riel' => 24600,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $userId,
            'last_updated_by' => $userId,
        ]);

        $aSale = ProductMovement::create([
            'product_id' => $productA->id,
            'direction' => StockDirectionEnum::OUT->value,
            'movement_type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => 10,
            'remaining_quantity' => 0,
            'is_sold' => true,
            'movement_date' => now()->subDays(4)->toDateTimeString(),
            'purchase_unit_price_in_usd' => 3.5,
            'purchase_total_price_in_usd' => 35,
            'purchase_unit_price_in_riel' => 14350,
            'purchase_total_price_in_riel' => 143500,
            'exchange_rate_from_usd_to_riel' => 4100,
            'exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'selling_unit_price_in_usd' => 5,
            'selling_unit_price_in_riel' => 20500,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $userId,
            'last_updated_by' => $userId,
        ]);

        ProductMovementAllocation::create([
            'sale_movement_id' => $aSale->id,
            'source_movement_id' => $aLot1->id,
            'allocated_quantity' => 10,
            'selling_unit_price_in_usd' => 5,
            'selling_unit_price_in_riel' => 20500,
            'cost_unit_price_in_usd' => 3.5,
            'cost_unit_price_in_riel' => 14350,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'cost_exchange_rate_from_usd_to_riel' => 4100,
            'cost_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'allocated_at' => now()->subDays(4)->toDateTimeString(),
            'created_by' => $userId,
        ]);

        // Product B: LIFO with latest reorder lot partially consumed.
        $productB = Product::factory()->internal()->create([
            'product_name' => 'Seed Product B (LIFO Lots)',
            'sale_method' => 'LIFO',
        ]);

        ProductMovement::create([
            'product_id' => $productB->id,
            'direction' => StockDirectionEnum::IN->value,
            'movement_type' => ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => 500,
            'remaining_quantity' => 500,
            'is_sold' => false,
            'movement_date' => now()->subDays(8)->toDateTimeString(),
            'purchase_unit_price_in_usd' => 0,
            'purchase_total_price_in_usd' => 0,
            'purchase_unit_price_in_riel' => 0,
            'purchase_total_price_in_riel' => 0,
            'exchange_rate_from_usd_to_riel' => 0,
            'exchange_rate_from_riel_to_usd' => 0,
            'selling_unit_price_in_usd' => 4,
            'selling_unit_price_in_riel' => 16400,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $userId,
            'last_updated_by' => $userId,
        ]);

        $bLot2 = ProductMovement::create([
            'product_id' => $productB->id,
            'direction' => StockDirectionEnum::IN->value,
            'movement_type' => ProductStockMovementTypeEnum::RE_ORDER->value,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => 50,
            'remaining_quantity' => 40,
            'is_sold' => true,
            'movement_date' => now()->subDays(3)->toDateTimeString(),
            'purchase_unit_price_in_usd' => 0,
            'purchase_total_price_in_usd' => 0,
            'purchase_unit_price_in_riel' => 0,
            'purchase_total_price_in_riel' => 0,
            'exchange_rate_from_usd_to_riel' => 0,
            'exchange_rate_from_riel_to_usd' => 0,
            'selling_unit_price_in_usd' => 4.5,
            'selling_unit_price_in_riel' => 18450,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $userId,
            'last_updated_by' => $userId,
        ]);

        $bSale = ProductMovement::create([
            'product_id' => $productB->id,
            'direction' => StockDirectionEnum::OUT->value,
            'movement_type' => ProductStockMovementTypeEnum::SALE_ORDER->value,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'quantity' => 10,
            'remaining_quantity' => 0,
            'is_sold' => true,
            'movement_date' => now()->subDays(2)->toDateTimeString(),
            'purchase_unit_price_in_usd' => 0,
            'purchase_total_price_in_usd' => 0,
            'purchase_unit_price_in_riel' => 0,
            'purchase_total_price_in_riel' => 0,
            'exchange_rate_from_usd_to_riel' => 0,
            'exchange_rate_from_riel_to_usd' => 0,
            'selling_unit_price_in_usd' => 4.5,
            'selling_unit_price_in_riel' => 18450,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'created_by' => $userId,
            'last_updated_by' => $userId,
        ]);

        ProductMovementAllocation::create([
            'sale_movement_id' => $bSale->id,
            'source_movement_id' => $bLot2->id,
            'allocated_quantity' => 10,
            'selling_unit_price_in_usd' => 4.5,
            'selling_unit_price_in_riel' => 18450,
            'cost_unit_price_in_usd' => 0,
            'cost_unit_price_in_riel' => 0,
            'selling_exchange_rate_from_usd_to_riel' => 4100,
            'selling_exchange_rate_from_riel_to_usd' => round(1 / 4100, 8),
            'cost_exchange_rate_from_usd_to_riel' => 0,
            'cost_exchange_rate_from_riel_to_usd' => 0,
            'allocated_at' => now()->subDays(2)->toDateTimeString(),
            'created_by' => $userId,
        ]);
    }
}
