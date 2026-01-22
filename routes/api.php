<?php

use App\Http\Controllers\API\AuthAPIController;
use App\Http\Controllers\API\CompanyInfoAPIController;
use App\Http\Controllers\API\CustomerCategoryAPIController;
use App\Http\Controllers\API\ProductCategoryAPIController;
use App\Http\Controllers\API\RawMaterialCategoryAPIController;
use App\Http\Controllers\API\SupplierAPIController;
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
Route::post('/send-reset-link', [AuthAPIController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthAPIController::class, 'resetPassword']);
Route::post('/users/verify-email', [UserAPIController::class, 'verifyEmail']);


Route::middleware(['auth:api', 'role:ADMIN'])->group(function () {
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

    Route::prefix('suppliers')->group(function () {
        Route::post('/import', [SupplierAPIController::class, 'import']);
        Route::get('/import-histories', [SupplierAPIController::class, 'getImportHistories']);
        Route::get('/', [SupplierAPIController::class, 'index']);
        Route::post('/', [SupplierAPIController::class, 'store']);
        Route::get('/{id}', [SupplierAPIController::class, 'show']);
        Route::patch('/{id}', [SupplierAPIController::class, 'update']);
     });


    Route::prefix('uoms')->group(function () {
        Route::get('/', [UOMAPIController::class, 'index']);
        Route::get('/{id}', [UOMAPIController::class, 'show']);
        Route::post('/', [UOMAPIController::class, 'create']);
        Route::patch('/{id}', [UOMAPIController::class, 'update']);
     });

    Route::prefix('warehouses')->group(function () {
        Route::get('/', [WarehouseAPIController::class, 'index']);
        Route::get('/{id}', [WarehouseAPIController::class, 'show']);
        Route::post('/', [WarehouseAPIController::class, 'store']);
        Route::patch('/{id}', [WarehouseAPIController::class, 'update']);
        Route::delete('/{warehouseId}/images/{imageId}', [WarehouseAPIController::class, 'deleteWarehouseImage']);
     });


    Route::prefix('raw-material-categories')->group(function () {
        Route::get('/', [RawMaterialCategoryAPIController::class, 'index']);
        Route::get('/{id}', [RawMaterialCategoryAPIController::class, 'show']);
        Route::post('/', [RawMaterialCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [RawMaterialCategoryAPIController::class, 'update']);
    });


    Route::prefix('product-categories')->group(function () {
        Route::get('/', [ProductCategoryAPIController::class, 'index']);
        Route::get('/{id}', [ProductCategoryAPIController::class, 'show']);
        Route::post('/', [ProductCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [ProductCategoryAPIController::class, 'update']);
    });


    Route::prefix('customer-categories')->group(function () {
        Route::get('/', [CustomerCategoryAPIController::class, 'index']);
        Route::get('/{id}', [CustomerCategoryAPIController::class, 'show']);
        Route::post('/', [CustomerCategoryAPIController::class, 'store']);
        Route::patch('/{id}', [CustomerCategoryAPIController::class, 'update']);
    });

});




// Route::middleware(['auth:api', 'role:STOCK_CONTROLLER'])->group(function () {
//     Route::prefix('users')->group(function () {
//         Route::get('/{id}', [UserAPIController::class, 'getUserById']);
//         Route::get('/', [UserAPIController::class, 'getUsers']);
//     }); 
// });


