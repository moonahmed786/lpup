<?php

use App\Http\Controllers\Api\ApiDocumentationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('docs/api', [ApiDocumentationController::class, 'index'])->name('docs.api');
Route::get('docs/openapi.json', [ApiDocumentationController::class, 'openApi'])->name('docs.openapi');
