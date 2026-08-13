<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\FamilyAuthController;
use App\Http\Controllers\Family\FamilyAuthorizedContactController;
use App\Http\Controllers\Family\FamilyHistoryController;
use App\Http\Controllers\Family\FamilyPasswordController;
use App\Http\Controllers\Family\FamilyProfileController;
use App\Http\Controllers\Family\FamilyStudentController;
use App\Http\Controllers\Family\KinderBrandingController;
use Illuminate\Support\Facades\Route;

Route::get('/kinder/branding', [KinderBrandingController::class, 'show']);

Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/family/login', [FamilyAuthController::class, 'login']);
Route::post('/family/password/forgot', [FamilyPasswordController::class, 'forgot']);
Route::post('/family/password/reset', [FamilyPasswordController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/admin/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('family')->prefix('family')->group(function () {
        Route::get('/me', [FamilyProfileController::class, 'show']);
        Route::patch('/me', [FamilyProfileController::class, 'update']);
        Route::patch('/password', [FamilyPasswordController::class, 'update']);
        Route::get('/students', [FamilyStudentController::class, 'index']);
        Route::get('/students/{student}/authorized', [FamilyAuthorizedContactController::class, 'index']);
        Route::post('/students/{student}/authorized', [FamilyAuthorizedContactController::class, 'store']);
        Route::delete('/students/{student}/authorized/{authorized}', [FamilyAuthorizedContactController::class, 'destroy']);
        Route::get('/history', [FamilyHistoryController::class, 'show']);
    });
});
