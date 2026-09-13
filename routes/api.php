<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/organization',        [OrganizationController::class, 'show']);
    Route::post('/organization',       [OrganizationController::class, 'store']);
    Route::get('/organization/status', [OrganizationController::class, 'status']);

    Route::get('/reviews', [ReviewController::class, 'index']);
});