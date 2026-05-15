<?php

use App\Http\Controllers\API\AuditLogAPIController;
use App\Http\Controllers\API\AuthAPIController;
use App\Http\Controllers\API\CompanyInfoAPIController;
use App\Http\Controllers\API\CustomerAPIController;
use App\Http\Controllers\API\CustomerCategoryAPIController;
use App\Http\Controllers\API\InventoryDashboardAPIController;
use App\Http\Controllers\API\ProductAPIController;
use App\Http\Controllers\API\ProductCategoryAPIController;
use App\Http\Controllers\API\RawMaterialAPIController;
use App\Http\Controllers\API\RMStockMovementAPIController;
use App\Http\Controllers\API\RawMaterialCategoryAPIController;
use App\Http\Controllers\API\RoleAPIController;
use App\Http\Controllers\API\SaleOrderAPIController;
use App\Http\Controllers\API\SupplierAPIController;
use App\Http\Controllers\API\TwoFactorAPIController;
use App\Http\Controllers\API\UOMAPIController;
use App\Http\Controllers\API\UomCategoryAPIController;
use App\Http\Controllers\API\UserAPIController;
use App\Http\Controllers\API\WarehouseAPIController;
use App\Http\Controllers\TestAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/login', [AuthAPIController::class, 'login']);
Route::post('/login/2fa', [AuthAPIController::class, 'verifyTwoFactor']);
Route::post('/send-reset-link', [AuthAPIController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthAPIController::class, 'resetPassword']);
Route::post('/users/verify-email', [UserAPIController::class, 'verifyEmail']);
Route::middleware('auth:api')->get('/auth/me', [AuthAPIController::class, 'me']);

Route::middleware(['auth:api', 'permission:dashboard.read'])
    ->prefix('v2/inventory-dashboard')
    ->group(function () {
        Route::get('/summary', [InventoryDashboardAPIController::class, 'summary']);
    });




Route::middleware(['auth:api'])->group(function () {
    Route::prefix('two-factor')->group(function () {
        Route::post('/setup', [TwoFactorAPIController::class, 'setup']);
        Route::post('/confirm', [TwoFactorAPIController::class, 'confirm']);
        Route::post('/disable', [TwoFactorAPIController::class, 'disable']);
    });

    Route::prefix('company')->group(function () {
        Route::get('/info', [CompanyInfoAPIController::class, 'getCompanyInfo'])->middleware('permission:company.read');
        Route::post('/general-info', [CompanyInfoAPIController::class, 'updateGeneral'])->middleware('permission:company.update');
        Route::post('/address-info', [CompanyInfoAPIController::class, 'updateAddress'])->middleware('permission:company.update');
        Route::post('/telegram-info', [CompanyInfoAPIController::class, 'updateTelegram'])->middleware('permission:company.update');
        Route::post('/setup-payment', [CompanyInfoAPIController::class, 'setupPayment'])->middleware('permission:company.update');
    });    
});

Route::middleware(['auth:api', 'permission:audit_logs.read'])->prefix('audit-logs')->group(function () {
    Route::get('/', [AuditLogAPIController::class, 'index']);
    Route::get('/{id}', [AuditLogAPIController::class, 'show']);
});

Route::middleware(['auth:api'])->prefix('users')->group(function () {
    Route::get('/statistics', [UserAPIController::class, 'getUserStatistics'])->middleware('permission:users.read_all');
    Route::get('/', [UserAPIController::class, 'getUsers'])->middleware('permission:users.read_all');
    Route::post('/', [UserAPIController::class, 'createUser'])->middleware('permission:users.create');
    Route::get('/{id}', [UserAPIController::class, 'getUserById'])->middleware('permission:users.read_all,users.read_own');
    Route::patch('/{id}', [UserAPIController::class, 'updateUser'])->middleware('permission:users.update_all,users.update_own');
});

Route::middleware(['auth:api'])->prefix('roles')->group(function () {
    Route::get('/', [RoleAPIController::class, 'index'])->middleware('permission:roles.read_all');
    Route::get('/permissions', [RoleAPIController::class, 'permissions'])->middleware('permission:roles.read_all');
    Route::get('/select-options', [RoleAPIController::class, 'selectOptions'])->middleware('permission:roles.read_all,users.create,users.update_all,users.update_own');
    Route::get('/{id}', [RoleAPIController::class, 'show'])->middleware('permission:roles.read_all');
    Route::post('/', [RoleAPIController::class, 'store'])->middleware('permission:roles.create');
    Route::patch('/{id}', [RoleAPIController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('/{id}', [RoleAPIController::class, 'destroy'])->middleware('permission:roles.delete');
    Route::put('/{id}/permissions', [RoleAPIController::class, 'updatePermissions'])->middleware('permission:roles.assign_permissions');
});


// Shared read-only routes for STOCK_CONTROLLER and VENDER
Route::middleware(['auth:api'])->group(function () {
    Route::prefix('product-categories')->middleware('permission:product_categories.read_all,product_categories.read_own')->group(function () {
        Route::get('/', [ProductCategoryAPIController::class, 'index']);
        Route::get('/{id}', [ProductCategoryAPIController::class, 'show']);
        Route::get('/trashed', [ProductCategoryAPIController::class, 'trashed']);
    });

    Route::prefix('products')->middleware('permission:products.read_all,products.read_own')->group(function () {
        Route::get('/', [ProductAPIController::class, 'index']);
        Route::get('/{id}/movements', [ProductAPIController::class, 'movements']);
        Route::get('/{id}/stock-lots', [ProductAPIController::class, 'stockLots']);
        Route::get('/{id}/scrap-eligible-stock-lots', [ProductAPIController::class, 'scrapEligibleStockLots']);
        Route::get('/{id}/pnl-detail', [ProductAPIController::class, 'pnlDetail']);
        Route::get('/{id}/bom-summary', [ProductAPIController::class, 'bomSummary']);
        Route::get('/{productId}/movements/{movementId}/bom-summary', [ProductAPIController::class, 'movementBomSummary']);
        Route::get('/trashed', [ProductAPIController::class, 'trashed']);
        Route::get('/{id}', [ProductAPIController::class, 'show']);
        Route::post('/{id}/sale-allocation-preview', [ProductAPIController::class, 'saleAllocationPreview']);
    });
});

// Protected inventory management routes
Route::middleware(['auth:api'])->group(function () {


    Route::prefix('raw-materials')->group(function () {
        Route::get('/' , [RawMaterialAPIController::class , 'index'] )->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::get('/{rawMaterialId}/movements', [RawMaterialAPIController::class, 'indexMovement'])->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::get('/{rawMaterialId}/stock-lots', [RawMaterialAPIController::class, 'stockLots'])->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::get('/{rawMaterialId}/scrap-eligible-stock-lots', [RawMaterialAPIController::class, 'scrapEligibleStockLots'])->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::post('/{rawMaterialId}/production-allocation-preview', [RawMaterialAPIController::class, 'productionAllocationPreview'])->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::get('/deleted' , [RawMaterialAPIController::class , 'allDeleted'])->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::get('/{id}' , [RawMaterialAPIController::class , 'show'] )->middleware('permission:raw_materials.read_all,raw_materials.read_own');
        Route::post('/create' , [RawMaterialAPIController::class , 'store'] )->middleware('permission:raw_materials.create');
        Route::patch('/{id}', [RawMaterialAPIController::class, 'update'])->middleware('permission:raw_materials.update_all,raw_materials.update_own');
        Route::delete('/{id}', [RawMaterialAPIController::class, 'delete'])->middleware('permission:raw_materials.delete_all,raw_materials.delete_own');
        Route::patch('/{id}/recover', [RawMaterialAPIController::class, 'recover'])->middleware('permission:raw_materials.update_all,raw_materials.update_own');
        Route::post('/{rawMaterialId}/images', [RawMaterialAPIController::class, 'uploadImages'])->middleware('permission:raw_materials.update_all,raw_materials.update_own');
        Route::delete('/{rawMaterialId}/images', [RawMaterialAPIController::class, 'deleteImages'])->middleware('permission:raw_materials.update_all,raw_materials.update_own');
        Route::post('/{rawMaterialId}/reorder', [RawMaterialAPIController::class, 'reorder'])->middleware('permission:raw_materials.create_reorder');
        Route::patch('/{rawMaterialId}/reorder/{movementId}', [RawMaterialAPIController::class, 'updateReorder'])->middleware('permission:raw_materials.update_reorder_all,raw_materials.update_reorder_own');
        Route::post('/{rawMaterialId}/adjustment-out', [RawMaterialAPIController::class, 'adjustmentOut'])->middleware('permission:raw_materials.update_all,raw_materials.update_own');
        Route::post('/{rawMaterialId}/scraps', [RawMaterialAPIController::class, 'createScrap'])->middleware('permission:raw_materials.create_scrap');
        Route::post('/{rawMaterialId}/stock-movements', [RMStockMovementAPIController::class, 'store'])->middleware('permission:raw_materials.update_all,raw_materials.update_own');

    });

    Route::prefix('suppliers')->group(function () {
        Route::post('/import', [SupplierAPIController::class, 'import'])->middleware('permission:suppliers.import');
        Route::get('/import-histories', [SupplierAPIController::class, 'getImportHistories'])->middleware('permission:suppliers.read_history');
        Route::get('/statistics', [SupplierAPIController::class, 'getSupplierStatistics'])->middleware('permission:suppliers.read_all,suppliers.read_own');
        Route::get('/deleted', [SupplierAPIController::class, 'allDeleted'])->middleware('permission:suppliers.read_all,suppliers.read_own');
        Route::get('/{id}/transactions', [SupplierAPIController::class, 'transactions'])->middleware('permission:suppliers.read_all,suppliers.read_own');
        Route::get('/', [SupplierAPIController::class, 'index'])->middleware('permission:suppliers.read_all,suppliers.read_own');
        Route::post('/', [SupplierAPIController::class, 'store'])->middleware('permission:suppliers.create');
        Route::get('/{id}', [SupplierAPIController::class, 'show'])->middleware('permission:suppliers.read_all,suppliers.read_own');
        Route::patch('/{id}', [SupplierAPIController::class, 'update'])->middleware('permission:suppliers.update_all,suppliers.update_own');
        Route::delete('/{id}' , [SupplierAPIController::class , 'delete'] )->middleware('permission:suppliers.update_all,suppliers.update_own');
        Route::patch('/{id}/recover', [SupplierAPIController::class, 'recover'])->middleware('permission:suppliers.recovery');
     });


    Route::prefix('uoms')->group(function () {
        Route::get('/trashed', [UOMAPIController::class, 'trashed'])->middleware('permission:uom.read_all,uom.read_own');
        Route::get('/', [UOMAPIController::class, 'index'])->middleware('permission:uom.read_all,uom.read_own');
        Route::post('/convert', [UOMAPIController::class, 'convert'])->middleware('permission:uom.read_all,uom.read_own');
        Route::get('/{id}', [UOMAPIController::class, 'show'])->middleware('permission:uom.read_all,uom.read_own');
        Route::post('/', [UOMAPIController::class, 'create'])->middleware('permission:uom.create');
        Route::patch('/{id}', [UOMAPIController::class, 'update'])->middleware('permission:uom.update_all,uom.update_own');
        Route::delete('/{id}', [UOMAPIController::class, 'delete'])->middleware('permission:uom.delete_all,uom.delete_own');
        Route::patch('/{id}/restore', [UOMAPIController::class, 'restore'])->middleware('permission:uom.update_all,uom.update_own');
     });

    Route::prefix('uom-categories')->group(function () {
        Route::get('/trashed', [UomCategoryAPIController::class, 'trashed'])->middleware('permission:uom.read_all,uom.read_own');
        Route::get('/', [UomCategoryAPIController::class, 'index'])->middleware('permission:uom.read_all,uom.read_own');
        Route::get('/{id}', [UomCategoryAPIController::class, 'show'])->middleware('permission:uom.read_all,uom.read_own');
        Route::post('/', [UomCategoryAPIController::class, 'store'])->middleware('permission:uom.create');
        Route::patch('/{id}', [UomCategoryAPIController::class, 'update'])->middleware('permission:uom.update_all,uom.update_own');
        Route::delete('/{id}', [UomCategoryAPIController::class, 'delete'])->middleware('permission:uom.delete_all,uom.delete_own');
        Route::patch('/{id}/restore', [UomCategoryAPIController::class, 'restore'])->middleware('permission:uom.update_all,uom.update_own');
    });

    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseAPIController::class, 'index'])->middleware('permission:warehouses.read_all,warehouses.read_own');
        Route::get('/{id}', [WarehouseAPIController::class, 'show'])->middleware('permission:warehouses.read_all,warehouses.read_own');
        Route::post('/', [WarehouseAPIController::class, 'store'])->middleware('permission:warehouses.create');
        Route::patch('/{id}', [WarehouseAPIController::class, 'update'])->middleware('permission:warehouses.update_all,warehouses.update_own');
        Route::delete('/{id}', [WarehouseAPIController::class, 'delete'])->middleware('permission:warehouses.delete_all,warehouses.delete_own');
        Route::delete('/{warehouseId}/images/{imageId}', [WarehouseAPIController::class, 'deleteWarehouseImage'])->middleware('permission:warehouses.update_all,warehouses.update_own');
        Route::patch('/{warehouseId}/sub-warehouses/{subWarehouseId}', [WarehouseAPIController::class, 'updateSubWarehouse'])->middleware('permission:warehouses.update_all,warehouses.update_own');
        Route::delete('/{warehouseId}/sub-warehouses/{subWarehouseId}', [WarehouseAPIController::class, 'deleteSubWarehouse'])->middleware('permission:warehouses.update_all,warehouses.update_own');
     });


    Route::prefix('raw-material-categories')->group(function () {
        Route::get('/', [RawMaterialCategoryAPIController::class, 'index'])->middleware('permission:raw_material_categories.read_all,raw_material_categories.read_own');
        Route::get('/{id}', [RawMaterialCategoryAPIController::class, 'show'])->middleware('permission:raw_material_categories.read_all,raw_material_categories.read_own');
        Route::post('/', [RawMaterialCategoryAPIController::class, 'store'])->middleware('permission:raw_material_categories.create');
        Route::patch('/{id}', [RawMaterialCategoryAPIController::class, 'update'])->middleware('permission:raw_material_categories.update_all,raw_material_categories.update_own');
        Route::delete('/{id}', [RawMaterialCategoryAPIController::class, 'delete'])->middleware('permission:raw_material_categories.delete_all');
        Route::patch('/{id}/restore', [RawMaterialCategoryAPIController::class, 'restore'])->middleware('permission:raw_material_categories.update_all,raw_material_categories.update_own');
    });


    Route::prefix('product-categories')->group(function () {
        Route::post('/', [ProductCategoryAPIController::class, 'store'])->middleware('permission:product_categories.create');
        Route::patch('/{id}', [ProductCategoryAPIController::class, 'update'])->middleware('permission:product_categories.update_all,product_categories.update_own');
        Route::delete('/{id}', [ProductCategoryAPIController::class, 'delete'])->middleware('permission:product_categories.delete_all');
        Route::patch('/{id}/restore', [ProductCategoryAPIController::class, 'restore'])->middleware('permission:product_categories.update_all,product_categories.update_own');
    });

    Route::prefix('products')->group(function () {
        Route::post('/{id}/sale-allocation-preview', [ProductAPIController::class, 'saleAllocationPreview'])->middleware('permission:products.read_all,products.read_own');
        Route::post('/create/external-purchase',      [ProductAPIController::class, 'storeExternalPurchase'])->middleware('permission:products.create');
        Route::post('/create/internal-manufacturing', [ProductAPIController::class, 'storeInternalManufacturing'])->middleware('permission:products.create');
        Route::delete('/{id}', [ProductAPIController::class, 'delete'])->middleware('permission:products.delete_all,products.delete_own');
        Route::patch('/{id}/restore', [ProductAPIController::class, 'restore'])->middleware('permission:products.update_all,products.update_own');

        // Reorder product (Create/Update) by external purchase (add stock)
        Route::post('/{id}/reorder/external-purchase', [ProductAPIController::class, 'reorderExternalPurchase'])->middleware('permission:products.create_reorder');
        Route::patch('/{productId}/reorder/external-purchase/{movementId}', [ProductAPIController::class, 'updateReorderExternalPurchase'])->middleware('permission:products.update_reorder_all,products.update_reorder_own');
        Route::get('/{productId}/reorder/external-purchase/{movementId}', [ProductAPIController::class, 'getReorderExternalPurchase'])->middleware('permission:products.read_all,products.read_own');
        Route::delete('/{productId}/reorder/external-purchase/{movementId}', [ProductAPIController::class, 'deleteReorderExternalPurchase'])->middleware('permission:products.update_reorder_all,products.update_reorder_own');



        // Reorder product (Create/Update) by internal manufacturing (add stock)
        Route::post('/{id}/reorder/internal-manufacturing', [ProductAPIController::class, 'reorderInternalManufacturing'])->middleware('permission:products.create_reorder');
        Route::patch('/{productId}/reorder/internal-manufacturing/{movementId}', [ProductAPIController::class, 'updateReorderInternalManufacturing'])->middleware('permission:products.update_reorder_all,products.update_reorder_own');
        Route::get('/{productId}/reorder/internal-manufacturing/{movementId}', [ProductAPIController::class, 'getReorderInternalManufacturing'])->middleware('permission:products.read_all,products.read_own');
        Route::delete('/{productId}/reorder/internal-manufacturing/{movementId}', [ProductAPIController::class, 'deleteReorderInternalManufacturing'])->middleware('permission:products.update_reorder_all,products.update_reorder_own');

        // Product update endpoints for initial movement (no movementId required)
        Route::patch('/{id}/update/external-purchase', [ProductAPIController::class, 'updateExternalPurchase'])->middleware('permission:products.update_all,products.update_own');
        Route::patch('/{id}/update/internal-manufacturing', [ProductAPIController::class, 'updateInternalManufacturing'])->middleware('permission:products.update_all,products.update_own');
        Route::post('/{id}/scrap', [ProductAPIController::class, 'createScrap'])->middleware('permission:products.create_scrap');
        Route::post('/{id}/scraps', [ProductAPIController::class, 'createScraps'])->middleware('permission:products.create_scrap');
        Route::get('/{id}/scrap-eligible-stock-lots', [ProductAPIController::class, 'scrapEligibleStockLots'])->middleware('permission:products.read_all,products.read_own');
        Route::get('/{id}/bom-summary', [ProductAPIController::class, 'bomSummary'])->middleware('permission:products.read_all,products.read_own');
        Route::get('/{productId}/movements/{movementId}/bom-summary', [ProductAPIController::class, 'movementBomSummary'])->middleware('permission:products.read_all,products.read_own');
        Route::post('/{id}/images', [ProductAPIController::class, 'uploadImages'])->middleware('permission:products.update_all,products.update_own');
        Route::delete('/{productId}/images/{imageId}', [ProductAPIController::class, 'deleteImage'])->middleware('permission:products.update_all,products.update_own');
        Route::patch('/{productId}/images/{imageId}/primary', [ProductAPIController::class, 'setPrimaryImage'])->middleware('permission:products.update_all,products.update_own');
        Route::get('/{productId}/scrap/{movementId}', [ProductAPIController::class, 'getScrap'])->middleware('permission:products.read_all,products.read_own');
        Route::patch('/{productId}/scrap/{movementId}', [ProductAPIController::class, 'updateScrap'])->middleware('permission:products.update_scrap_all,products.update_scrap_own');
    });

    
    // Test route for product PnL
    Route::prefix('test')->group(function () {
        Route::get('/{productId}/pnl-test', [TestAPIController::class, 'show']);
    });
    

});



// Customer and sales routes
Route::middleware(['auth:api'])->group(function () {

    // Customer Categories Routes 
    Route::prefix('customer-categories')->group(function () {
        Route::get('/', [CustomerCategoryAPIController::class, 'index'])->middleware('permission:customer_categories.read_all,customer_categories.read_own');
        Route::get('/trashed', [CustomerCategoryAPIController::class, 'trashed'])->middleware('permission:customer_categories.read_all,customer_categories.read_own');
        Route::get('/{id}', [CustomerCategoryAPIController::class, 'show'])->middleware('permission:customer_categories.read_all,customer_categories.read_own');
        Route::post('/', [CustomerCategoryAPIController::class, 'store'])->middleware('permission:customer_categories.create');
        Route::patch('/{id}', [CustomerCategoryAPIController::class, 'update'])->middleware('permission:customer_categories.update_all,customer_categories.update_own');
        Route::delete('/{id}', [CustomerCategoryAPIController::class, 'delete'])->middleware('permission:customer_categories.delete_all');
        Route::patch('/{id}/restore', [CustomerCategoryAPIController::class, 'restore'])->middleware('permission:customer_categories.update_all,customer_categories.update_own');
    });

    // Customers Routes
    Route::prefix('customers')->group(function () {
        Route::get('/pos-search', [CustomerAPIController::class, 'posSearch'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/walk-in', [CustomerAPIController::class, 'walkIn'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/segmented', [CustomerAPIController::class, 'segmented'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/{id}/profile', [CustomerAPIController::class, 'profile'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/{id}/stats', [CustomerAPIController::class, 'stats'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/{id}/timeline', [CustomerAPIController::class, 'timeline'])->middleware('permission:customers.read_all,customers.read_own');
        Route::post('/addresses/default', [CustomerAPIController::class, 'setDefaultAddress'])->middleware('permission:customers.update_all,customers.update_own');
        Route::post('/{id}/credit/can-purchase', [CustomerAPIController::class, 'canPurchase'])->middleware('permission:customers.read_all,customers.read_own');
        Route::post('/{id}/credit/apply-sale', [CustomerAPIController::class, 'applySale'])->middleware('permission:customers.update_all,customers.update_own');
        Route::post('/{id}/credit/apply-payment', [CustomerAPIController::class, 'applyPayment'])->middleware('permission:customers.update_all,customers.update_own');
        Route::post('/{id}/tags/attach', [CustomerAPIController::class, 'attachTags'])->middleware('permission:customers.update_all,customers.update_own');
        Route::put('/{id}/tags/sync', [CustomerAPIController::class, 'syncTags'])->middleware('permission:customers.update_all,customers.update_own');
        Route::delete('/{id}/tags/{tagId}', [CustomerAPIController::class, 'detachTag'])->middleware('permission:customers.update_all,customers.update_own');
        Route::get('/', [CustomerAPIController::class, 'index'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/trashed', [CustomerAPIController::class, 'trashed'])->middleware('permission:customers.read_all,customers.read_own');
        Route::get('/{id}', [CustomerAPIController::class, 'show'])->middleware('permission:customers.read_all,customers.read_own');
        Route::post('/', [CustomerAPIController::class, 'store'])->middleware('permission:customers.create');
        Route::patch('/{id}', [CustomerAPIController::class, 'update'])->middleware('permission:customers.update_all,customers.update_own');
        Route::delete('/{id}', [CustomerAPIController::class, 'destroy'])->middleware('permission:customers.delete_all,customers.delete_own');
        Route::patch('/{id}/restore', [CustomerAPIController::class, 'restore'])->middleware('permission:customers.update_all,customers.update_own');
     });

    Route::prefix('sale-orders')->group(function () {
        Route::get('/', [SaleOrderAPIController::class, 'index'])->middleware('permission:sale_orders.read_all,sale_orders.read_own');
        Route::get('/statistics', [SaleOrderAPIController::class, 'statistics'])->middleware('permission:sale_orders.read_sale_dashboard');
        Route::get('/statistics/report', [SaleOrderAPIController::class, 'statisticsReport'])->middleware('permission:sale_orders.read_sale_dashboard');
        Route::get('/{id}/report', [SaleOrderAPIController::class, 'saleOrderReport'])->middleware('permission:sale_orders.read_all,sale_orders.read_own');
        Route::get('/refund-records', [SaleOrderAPIController::class, 'refundRecords'])->middleware('permission:sale_orders.read_all,sale_orders.read_own');
        Route::get('/stock-availability/{productId}', [SaleOrderAPIController::class, 'getStockAvailability'])->middleware('permission:sale_orders.read_all,sale_orders.read_own');
        Route::get('/{id}', [SaleOrderAPIController::class, 'show'])->middleware('permission:sale_orders.read_all,sale_orders.read_own');
        Route::get('/{id}/refunds', [SaleOrderAPIController::class, 'refunds'])->middleware('permission:sale_orders.read_all,sale_orders.read_own');
        Route::post('/', [SaleOrderAPIController::class, 'store'])->middleware('permission:sale_orders.create');
        Route::post('/{id}/payments', [SaleOrderAPIController::class, 'addPayment'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
        Route::post('/{id}/installments', [SaleOrderAPIController::class, 'addInstallment'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
        Route::patch('/{id}/installments/latest', [SaleOrderAPIController::class, 'updateLatestInstallment'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
        Route::patch('/{id}', [SaleOrderAPIController::class, 'update'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
        Route::patch('/{id}/status', [SaleOrderAPIController::class, 'updateStatus'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
        Route::patch('/{id}/refund', [SaleOrderAPIController::class, 'refund'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
        Route::delete('/{id}', [SaleOrderAPIController::class, 'delete'])->middleware('permission:sale_orders.update_all,sale_orders.update_own');
    });

});
