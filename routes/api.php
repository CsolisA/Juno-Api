<?php

use App\Http\Controllers\Admin\PreEnrollment\CampaignController;
use App\Http\Controllers\Admin\PreEnrollment\ExclusionController;
use App\Http\Controllers\Admin\PreEnrollment\FormController;
use App\Http\Controllers\Admin\PreEnrollment\ReadinessController;
use App\Http\Controllers\Admin\PreEnrollment\SelectionController;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\FamilyAuthController;
use App\Http\Controllers\Catalog\AcademicYearCatalogController;
use App\Http\Controllers\Catalog\CantonCatalogController;
use App\Http\Controllers\Catalog\GradeCatalogController;
use App\Http\Controllers\Catalog\ProvinceCatalogController;
use App\Http\Controllers\Catalog\ScheduleCatalogController;
use App\Http\Controllers\Catalog\StaticCatalogController;
use App\Http\Controllers\Family\FamilyAuthorizedContactController;
use App\Http\Controllers\Family\FamilyHistoryController;
use App\Http\Controllers\Family\FamilyPasswordController;
use App\Http\Controllers\Family\FamilyProfileController;
use App\Http\Controllers\Family\FamilyStudentController;
use App\Http\Controllers\Family\KinderBrandingController;
use App\Http\Controllers\Family\PreEnrollmentController;
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

    // Shared reference lists — used by both the admin form-review screen and the family
    // pre-enrollment wizard, so these sit outside the admin-only / family-only guards.
    Route::prefix('catalogs')->group(function () {
        Route::get('/grades', [GradeCatalogController::class, 'index']);
        Route::get('/schedules', [ScheduleCatalogController::class, 'index']);
        Route::get('/provinces', [ProvinceCatalogController::class, 'index']);
        Route::get('/cantons', [CantonCatalogController::class, 'index']);
        Route::get('/relationships', [StaticCatalogController::class, 'relationships']);
        Route::get('/nationalities', [StaticCatalogController::class, 'nationalities']);
        Route::get('/education-levels', [StaticCatalogController::class, 'educationLevels']);
        Route::get('/marital-statuses', [StaticCatalogController::class, 'maritalStatuses']);
        Route::get('/blood-types', [StaticCatalogController::class, 'bloodTypes']);
    });

    Route::middleware('family')->prefix('family')->group(function () {
        Route::get('/me', [FamilyProfileController::class, 'show']);
        Route::patch('/me', [FamilyProfileController::class, 'update']);
        Route::patch('/password', [FamilyPasswordController::class, 'update']);
        Route::get('/students', [FamilyStudentController::class, 'index']);
        Route::get('/students/{student}/authorized', [FamilyAuthorizedContactController::class, 'index']);
        Route::post('/students/{student}/authorized', [FamilyAuthorizedContactController::class, 'store']);
        Route::delete('/students/{student}/authorized/{authorized}', [FamilyAuthorizedContactController::class, 'destroy']);
        Route::get('/history', [FamilyHistoryController::class, 'show']);
        Route::get('/pre-enrollment/active', [PreEnrollmentController::class, 'active']);
        Route::get('/pre-enrollment/{campaignId}', [PreEnrollmentController::class, 'show']);
        Route::patch('/pre-enrollment/{campaignId}/family', [PreEnrollmentController::class, 'updateFamilyDraft']);
        Route::patch('/pre-enrollment/{campaignId}/students/{studentId}', [PreEnrollmentController::class, 'updateStudentDraft']);
        Route::post('/pre-enrollment/{campaignId}/submit', [PreEnrollmentController::class, 'submit']);
        Route::get('/pre-enrollment/{campaignId}/receipt', [PreEnrollmentController::class, 'receipt']);
    });

    Route::middleware('admin')->group(function () {
        Route::get('/catalogs/academic-years', [AcademicYearCatalogController::class, 'index']);

        Route::prefix('admin/pre-enrollment')->group(function () {
            Route::get('/campaigns', [CampaignController::class, 'index']);
            Route::post('/campaigns', [CampaignController::class, 'store']);
            Route::get('/campaigns/{campaignId}', [CampaignController::class, 'show']);
            Route::post('/campaigns/{campaignId}/open', [CampaignController::class, 'open']);
            Route::post('/campaigns/{campaignId}/close', [CampaignController::class, 'close']);
            Route::get('/campaigns/{campaignId}/selection', [SelectionController::class, 'show']);
            Route::get('/campaigns/{campaignId}/readiness', [ReadinessController::class, 'show']);
            Route::patch('/campaigns/{campaignId}/forms/{formId}/exclusion', [ExclusionController::class, 'update']);
            Route::post('/campaigns/{campaignId}/forms/bulk-exclusion', [ExclusionController::class, 'bulk']);
            Route::get('/campaigns/{campaignId}/forms', [FormController::class, 'index']);
            Route::get('/campaigns/{campaignId}/forms/{formId}', [FormController::class, 'show']);
            Route::patch('/campaigns/{campaignId}/forms/{formId}', [FormController::class, 'update']);
            Route::patch('/campaigns/{campaignId}/forms/{formId}/target-grade', [FormController::class, 'targetGrade']);
            Route::post('/campaigns/{campaignId}/forms/{formId}/approve', [FormController::class, 'approve']);
            Route::post('/campaigns/{campaignId}/forms/{formId}/reopen', [FormController::class, 'reopen']);
            Route::get('/campaigns/{campaignId}/forms/{formId}/changes', [FormController::class, 'changes']);
        });
    });
});
