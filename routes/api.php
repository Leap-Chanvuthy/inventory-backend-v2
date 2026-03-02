<?php

use App\Http\Controllers\API\AuthAPIController;
use App\Http\Controllers\API\CompanyInfoAPIController;
use App\Http\Controllers\API\CustomerAPIController;
use App\Http\Controllers\API\CustomerCategoryAPIController;
use App\Http\Controllers\API\ProductCategoryAPIController;
use App\Http\Controllers\API\RawMaterialAPIController;
use App\Http\Controllers\API\RMStockMovementAPIController;
use App\Http\Controllers\API\RawMaterialCategoryAPIController;
use App\Http\Controllers\API\SupplierAPIController;
use App\Http\Controllers\API\TwoFactorAPIController;
use App\Http\Controllers\API\UOMAPIController;
use App\Http\Controllers\API\UserAPIController;
use App\Http\Controllers\API\WarehouseAPIController;
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
Route::middleware(['auth:api'])->group(function () {
    Route::prefix('two-factor')->group(function () {
        Route::post('/setup', [TwoFactorAPIController::class, 'setup']);
        Route::post('/confirm', [TwoFactorAPIController::class, 'confirm']);
        Route::post('/disable', [TwoFactorAPIController::class, 'disable']);
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


// Protected Routes for ADMIN and STOCK_CONTROLLER
Route::middleware(['auth:api', 'role:ADMIN' , 'role:STOCK_CONTROLLER'])->group(function () {


    Route::prefix('raw-materials')->group(function () {
        Route::get('/' , [RawMaterialAPIController::class , 'index'] );
        Route::get('/deleted' , [RawMaterialAPIController::class , 'allDeleted']);
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
        Route::get('/', [UOMAPIController::class, 'index']);
        Route::get('/{id}', [UOMAPIController::class, 'show']);
        Route::post('/', [UOMAPIController::class, 'create']);
        Route::patch('/{id}', [UOMAPIController::class, 'update']);
        Route::delete('/{id}', [UOMAPIController::class, 'delete']);
     });

    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseAPIController::class, 'index']);
        Route::get('/{id}', [WarehouseAPIController::class, 'show']);
        Route::post('/', [WarehouseAPIController::class, 'store']);
        Route::patch('/{id}', [WarehouseAPIController::class, 'update']);
        Route::delete('/{id}', [WarehouseAPIController::class, 'delete']);
        Route::delete('/{warehouseId}/images/{imageId}', [WarehouseAPIController::class, 'deleteWarehouseImage']);
     });


    Route::prefix('raw-material-categories')->group(function () {
        Route::get('/', [RawMaterialCategoryAPIController::class, 'index']);
        Route::get('/{id}', [RawMaterialCategoryAPIController::class, 'show']);
        Route::post('/', [RawMaterialCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [RawMaterialCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [RawMaterialCategoryAPIController::class, 'delete'] );
    });


    Route::prefix('product-categories')->group(function () {
        Route::get('/', [ProductCategoryAPIController::class, 'index']);
        Route::get('/{id}', [ProductCategoryAPIController::class, 'show']);
        Route::post('/', [ProductCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [ProductCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [ProductCategoryAPIController::class, 'delete'] );
    });

});



// Protected Routes for ADMIN and VENDER 
Route::middleware(['auth:api', 'role:ADMIN' , 'role:CUSTOMER'])->group(function () {

    // Customer Categories Routes 
    Route::prefix('customer-categories')->group(function () {
        Route::get('/', [CustomerCategoryAPIController::class, 'index']);
        Route::get('/{id}', [CustomerCategoryAPIController::class, 'show']);
        Route::post('/', [CustomerCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [CustomerCategoryAPIController::class, 'update']);
        Route::delete('/{id}', [CustomerCategoryAPIController::class, 'delete'] );
    });

    // Customers Routes
    Route::prefix('customers')->group(function () {
        Route::get('/', [CustomerAPIController::class, 'index']);
        Route::get('/{id}', [CustomerAPIController::class, 'show']);
        Route::post('/', [CustomerAPIController::class, 'store']);
        Route::patch('/{id}', [CustomerAPIController::class, 'update']);
        Route::delete('/{id}' , [CustomerAPIController::class , 'destroy'] );
     });

});




