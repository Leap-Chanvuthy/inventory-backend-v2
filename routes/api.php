<?php

use App\Http\Controllers\API\AuditLogAPIController;
use App\Http\Controllers\API\AuthAPIController;
use App\Http\Controllers\API\CompanyInfoAPIController;
use App\Http\Controllers\API\CustomerAPIController;
use App\Http\Controllers\API\CustomerCategoryAPIController;
use App\Http\Controllers\API\ProductAPIController;
use App\Http\Controllers\API\ProductCategoryAPIController;
use App\Http\Controllers\API\RawMaterialAPIController;
use App\Http\Controllers\API\RMStockMovementAPIController;
use App\Http\Controllers\API\RawMaterialCategoryAPIController;
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




// Protected Routes for ADMIN ONLY USERS
Route::middleware(['auth:api', 'role:ADMIN'])->group(function () {
    Route::prefix('two-factor')->group(function () {
        Route::post('/setup', [TwoFactorAPIController::class, 'setup']);
        Route::post('/confirm', [TwoFactorAPIController::class, 'confirm']);
        Route::post('/disable', [TwoFactorAPIController::class, 'disable']);
    });


    // Audit log routes - only accessible by ADMIN
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogAPIController::class, 'index']);
        Route::get('/{id}', [AuditLogAPIController::class, 'show']);
    });

    Route::prefix('company')->group(function () {
        Route::get('/info', [CompanyInfoAPIController::class, 'getCompanyInfo']);
        Route::post('/general-info', [CompanyInfoAPIController::class, 'updateGeneral']);
        Route::post('/address-info', [CompanyInfoAPIController::class, 'updateAddress']);
        Route::post('/telegram-info', [CompanyInfoAPIController::class, 'updateTelegram']);
        Route::post('/setup-payment', [CompanyInfoAPIController::class, 'setupPayment']);
    });    

    Route::prefix('users')->group(function () {
        Route::get('/statistics', [UserAPIController::class, 'getUserStatistics']);
        Route::get('/{id}', [UserAPIController::class, 'getUserById']);
        Route::get('/', [UserAPIController::class, 'getUsers']);
        Route::post('/', [UserAPIController::class, 'createUser']);
        Route::patch('/{id}', [UserAPIController::class, 'updateUser']);
    }); 
});


// Shared read-only routes for STOCK_CONTROLLER and VENDER
Route::middleware(['auth:api', 'role:STOCK_CONTROLLER,VENDER'])->group(function () {
    Route::prefix('product-categories')->group(function () {
        Route::get('/', [ProductCategoryAPIController::class, 'index']);
        Route::get('/{id}', [ProductCategoryAPIController::class, 'show']);
        Route::get('/trashed', [ProductCategoryAPIController::class, 'trashed']);
        Route::get('/{id}', [ProductCategoryAPIController::class, 'show']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductAPIController::class, 'index']);
        Route::get('/{id}/movements', [ProductAPIController::class, 'movements']);
        Route::get('/trashed', [ProductAPIController::class, 'trashed']);
        Route::get('/{id}', [ProductAPIController::class, 'show']);
    });
});

// Protected Routes for ADMIN and STOCK_CONTROLLER
Route::middleware(['auth:api' , 'role:STOCK_CONTROLLER'])->group(function () {


    Route::prefix('raw-materials')->group(function () {
        Route::get('/' , [RawMaterialAPIController::class , 'index'] );
        Route::get('/{rawMaterialId}/movements', [RawMaterialAPIController::class, 'indexMovement']);        Route::get('/deleted' , [RawMaterialAPIController::class , 'allDeleted']);
        Route::get('/{id}' , [RawMaterialAPIController::class , 'show'] );
        Route::post('/create' , [RawMaterialAPIController::class , 'store'] );
        Route::patch('/{id}', [RawMaterialAPIController::class, 'update']);
        Route::delete('/{id}', [RawMaterialAPIController::class, 'delete']);
        Route::patch('/{id}/recover', [RawMaterialAPIController::class, 'recover']);
        Route::delete('/{rawMaterialId}/images', [RawMaterialAPIController::class, 'deleteImages']);
        Route::post('/{rawMaterialId}/reorder', [RawMaterialAPIController::class, 'reorder']);
        Route::patch('/{rawMaterialId}/reorder/{movementId}', [RawMaterialAPIController::class, 'updateReorder']);
        Route::post('/{rawMaterialId}/adjustment-out', [RawMaterialAPIController::class, 'adjustmentOut']);
        Route::post('/{rawMaterialId}/stock-movements', [RMStockMovementAPIController::class, 'store']);

    });

    Route::prefix('suppliers')->group(function () {
        Route::post('/import', [SupplierAPIController::class, 'import']);
        Route::get('/import-histories', [SupplierAPIController::class, 'getImportHistories']);
        Route::get('/statistics', [SupplierAPIController::class, 'getSupplierStatistics']);
        Route::get('/deleted', [SupplierAPIController::class, 'allDeleted']);
        Route::get('/{id}/transactions', [SupplierAPIController::class, 'transactions']);
        Route::get('/', [SupplierAPIController::class, 'index']);
        Route::post('/', [SupplierAPIController::class, 'store']);
        Route::get('/{id}', [SupplierAPIController::class, 'show']);
        Route::patch('/{id}', [SupplierAPIController::class, 'update']);
        Route::delete('/{id}' , [SupplierAPIController::class , 'delete'] );
        Route::patch('/{id}/recover', [SupplierAPIController::class, 'recover']);
     });


    Route::prefix('uoms')->group(function () {
        Route::get('/trashed', [UOMAPIController::class, 'trashed']);
        Route::get('/', [UOMAPIController::class, 'index']);
        Route::post('/convert', [UOMAPIController::class, 'convert']);
        Route::get('/{id}', [UOMAPIController::class, 'show']);
        Route::post('/', [UOMAPIController::class, 'create']);
        Route::patch('/{id}', [UOMAPIController::class, 'update']);
        Route::delete('/{id}', [UOMAPIController::class, 'delete']);
        Route::patch('/{id}/restore', [UOMAPIController::class, 'restore']);
     });

    Route::prefix('uom-categories')->group(function () {
        Route::get('/trashed', [UomCategoryAPIController::class, 'trashed']);
        Route::get('/', [UomCategoryAPIController::class, 'index']);
        Route::get('/{id}', [UomCategoryAPIController::class, 'show']);
        Route::post('/', [UomCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [UomCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [UomCategoryAPIController::class, 'delete']);
        Route::patch('/{id}/restore', [UomCategoryAPIController::class, 'restore']);
    });

    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseAPIController::class, 'index']);
        Route::get('/{id}', [WarehouseAPIController::class, 'show']);
        Route::post('/', [WarehouseAPIController::class, 'store']);
        Route::patch('/{id}', [WarehouseAPIController::class, 'update']);
        Route::delete('/{id}', [WarehouseAPIController::class, 'delete']);
        Route::delete('/{warehouseId}/images/{imageId}', [WarehouseAPIController::class, 'deleteWarehouseImage']);
        Route::patch('/{warehouseId}/sub-warehouses/{subWarehouseId}', [WarehouseAPIController::class, 'updateSubWarehouse']);
        Route::delete('/{warehouseId}/sub-warehouses/{subWarehouseId}', [WarehouseAPIController::class, 'deleteSubWarehouse']);
     });


    Route::prefix('raw-material-categories')->group(function () {
        Route::get('/', [RawMaterialCategoryAPIController::class, 'index']);
        Route::get('/{id}', [RawMaterialCategoryAPIController::class, 'show']);
        Route::post('/', [RawMaterialCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [RawMaterialCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [RawMaterialCategoryAPIController::class, 'delete'] );
        Route::patch('/{id}/restore', [RawMaterialCategoryAPIController::class, 'restore']);
    });


    Route::prefix('product-categories')->group(function () {
        Route::post('/', [ProductCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [ProductCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [ProductCategoryAPIController::class, 'delete'] );
        Route::patch('/{id}/restore', [ProductCategoryAPIController::class, 'restore']);
    });

    Route::prefix('products')->group(function () {
        Route::post('/create/external-purchase',      [ProductAPIController::class, 'storeExternalPurchase']);
        Route::post('/create/internal-manufacturing', [ProductAPIController::class, 'storeInternalManufacturing']);
        Route::delete('/{id}', [ProductAPIController::class, 'delete']);
        Route::patch('/{id}/restore', [ProductAPIController::class, 'restore']);

        // Reorder product (Create/Update) by external purchase (add stock)
        Route::post('/{id}/reorder/external-purchase', [ProductAPIController::class, 'reorderExternalPurchase']);
        Route::patch('/{productId}/reorder/external-purchase/{movementId}', [ProductAPIController::class, 'updateReorderExternalPurchase']);
        Route::get('/{productId}/reorder/external-purchase/{movementId}', [ProductAPIController::class, 'getReorderExternalPurchase']);
        Route::delete('/{productId}/reorder/external-purchase/{movementId}', [ProductAPIController::class, 'deleteReorderExternalPurchase']);



        // Reorder product (Create/Update) by internal manufacturing (add stock)
        Route::post('/{id}/reorder/internal-manufacturing', [ProductAPIController::class, 'reorderInternalManufacturing']);
        Route::patch('/{productId}/reorder/internal-manufacturing/{movementId}', [ProductAPIController::class, 'updateReorderInternalManufacturing']);
        Route::get('/{productId}/reorder/internal-manufacturing/{movementId}', [ProductAPIController::class, 'getReorderInternalManufacturing']);
        Route::delete('/{productId}/reorder/internal-manufacturing/{movementId}', [ProductAPIController::class, 'deleteReorderInternalManufacturing']);

        // Product update endpoints for initial movement (no movementId required)
        Route::patch('/{id}/update/external-purchase', [ProductAPIController::class, 'updateExternalPurchase']);
        Route::patch('/{id}/update/internal-manufacturing', [ProductAPIController::class, 'updateInternalManufacturing']);
        Route::post('/{id}/scrap', [ProductAPIController::class, 'createScrap']);
        Route::get('/{productId}/scrap/{movementId}', [ProductAPIController::class, 'getScrap']);
        Route::patch('/{productId}/scrap/{movementId}', [ProductAPIController::class, 'updateScrap']);
    });

    
    // Test route for product PnL
    Route::prefix('test')->group(function () {
        Route::get('/{productId}/pnl-test', [TestAPIController::class, 'show']);
    });
    

});



// Protected Routes for ADMIN and VENDER 
Route::middleware(['auth:api' , 'role:VENDER'])->group(function () {

    // Customer Categories Routes 
    Route::prefix('customer-categories')->group(function () {
        Route::get('/', [CustomerCategoryAPIController::class, 'index']);
        Route::get('/trashed', [CustomerCategoryAPIController::class, 'trashed']);
        Route::get('/{id}', [CustomerCategoryAPIController::class, 'show']);
        Route::post('/', [CustomerCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [CustomerCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [CustomerCategoryAPIController::class, 'delete'] );
        Route::patch('/{id}/restore', [CustomerCategoryAPIController::class, 'restore']);
    });

    // Customers Routes
    Route::prefix('customers')->group(function () {
        Route::get('/pos-search', [CustomerAPIController::class, 'posSearch']);
        Route::get('/walk-in', [CustomerAPIController::class, 'walkIn']);
        Route::get('/segmented', [CustomerAPIController::class, 'segmented']);
        Route::get('/{id}/profile', [CustomerAPIController::class, 'profile']);
        Route::get('/{id}/stats', [CustomerAPIController::class, 'stats']);
        Route::get('/{id}/timeline', [CustomerAPIController::class, 'timeline']);
        Route::post('/addresses/default', [CustomerAPIController::class, 'setDefaultAddress']);
        Route::post('/{id}/credit/can-purchase', [CustomerAPIController::class, 'canPurchase']);
        Route::post('/{id}/credit/apply-sale', [CustomerAPIController::class, 'applySale']);
        Route::post('/{id}/credit/apply-payment', [CustomerAPIController::class, 'applyPayment']);
        Route::post('/{id}/tags/attach', [CustomerAPIController::class, 'attachTags']);
        Route::put('/{id}/tags/sync', [CustomerAPIController::class, 'syncTags']);
        Route::delete('/{id}/tags/{tagId}', [CustomerAPIController::class, 'detachTag']);
        Route::get('/', [CustomerAPIController::class, 'index']);
        Route::get('/trashed', [CustomerAPIController::class, 'trashed']);
        Route::get('/{id}', [CustomerAPIController::class, 'show']);
        Route::post('/', [CustomerAPIController::class, 'store']);
        Route::patch('/{id}', [CustomerAPIController::class, 'update']);
        Route::delete('/{id}' , [CustomerAPIController::class , 'destroy'] );
        Route::patch('/{id}/restore', [CustomerAPIController::class, 'restore']);
     });

    Route::prefix('sale-orders')->group(function () {
        Route::get('/', [SaleOrderAPIController::class, 'index']);
        Route::get('/statistics', [SaleOrderAPIController::class, 'statistics']);
        Route::get('/statistics/report', [SaleOrderAPIController::class, 'statisticsReport']);
        Route::get('/{id}/report', [SaleOrderAPIController::class, 'saleOrderReport']);
        Route::get('/refund-records', [SaleOrderAPIController::class, 'refundRecords']);
        Route::get('/stock-availability/{productId}', [SaleOrderAPIController::class, 'getStockAvailability']);
        Route::get('/{id}', [SaleOrderAPIController::class, 'show']);
        Route::get('/{id}/refunds', [SaleOrderAPIController::class, 'refunds']);
        Route::post('/', [SaleOrderAPIController::class, 'store']);
        Route::post('/{id}/payments', [SaleOrderAPIController::class, 'addPayment']);
        Route::post('/{id}/installments', [SaleOrderAPIController::class, 'addInstallment']);
        Route::patch('/{id}/installments/latest', [SaleOrderAPIController::class, 'updateLatestInstallment']);
        Route::patch('/{id}', [SaleOrderAPIController::class, 'update']);
        Route::patch('/{id}/status', [SaleOrderAPIController::class, 'updateStatus']);
        Route::patch('/{id}/refund', [SaleOrderAPIController::class, 'refund']);
        Route::delete('/{id}', [SaleOrderAPIController::class, 'delete']);
    });

});
