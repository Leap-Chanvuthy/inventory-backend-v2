<?php

use App\Http\Controllers\API\AuthAPIController;
use App\Http\Controllers\API\CompanyInfoAPIController;
use App\Http\Controllers\API\UserAPIController;
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


Route::prefix('company')->group(function () {
    Route::get('/info', [CompanyInfoAPIController::class, 'getCompanyInfo']);
    Route::post('/general-info', [CompanyInfoAPIController::class, 'updateGeneral']);
    Route::post('/address-info', [CompanyInfoAPIController::class, 'updateAddress']);
    Route::post('/telegram-info', [CompanyInfoAPIController::class, 'updateTelegram']);
    Route::post('/setup-payment', [CompanyInfoAPIController::class, 'setupPayment']);
});

Route::prefix('users')->group(function () {
    Route::get('/{id}', [UserAPIController::class, 'getUserById']);
    Route::get('/', [UserAPIController::class, 'getUsers']);
}); 
