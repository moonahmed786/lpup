<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Auth (token issuance is public; protected endpoints use Passport).
Route::get('login', [AuthController::class, 'loginInstructions'])->name('login.instructions');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

Route::middleware('auth:api')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');

    // Product CRUD. Per-action policy abilities are declared with #[Authorize]
    // attributes on ProductController.
    Route::apiResource('products', ProductController::class);
});
