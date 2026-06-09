<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Auth (token issuance is public; logout/me guard themselves via #[Middleware] attributes).
Route::post('login', [AuthController::class, 'login'])->name('login');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
Route::get('me', [AuthController::class, 'me'])->name('me');

// Product CRUD. Per-action auth (auth:api + policy abilities) is declared with
// #[Middleware] / #[Authorize] attributes on ProductController.
Route::apiResource('products', ProductController::class);
