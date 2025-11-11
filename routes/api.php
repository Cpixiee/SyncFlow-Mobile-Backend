<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MeasurementController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductMeasurementController;
use App\Http\Controllers\Api\V1\ProductCategoryController;
use App\Http\Controllers\Api\V1\MeasurementInstrumentController;
use App\Http\Controllers\Api\V1\ToolController;
use App\Http\Controllers\Api\V1\IssueController;

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    // Public authentication routes
    Route::post('/login', [AuthController::class, 'login']);
    
    // Protected routes - require JWT authentication
    Route::middleware('api.auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::put('/update-user', [AuthController::class, 'updateUser']);
        
        // Measurement routes - available for authenticated users
        Route::prefix('measurements')->group(function () {
            Route::post('/', [MeasurementController::class, 'store']);
            Route::get('/{id}', [MeasurementController::class, 'show']);
            Route::post('/{id}/process-formula', [MeasurementController::class, 'processFormula']);
        });

        // Product routes - Admin and SuperAdmin can CRUD
        Route::prefix('products')->group(function () {
            // Autocomplete endpoint - available for all authenticated users
            Route::get('/{productId}/measurement-items/suggest', [ProductController::class, 'suggestMeasurementItems']);
            
            // Admin and SuperAdmin only routes
            Route::middleware('role:admin,superadmin')->group(function () {
                Route::post('/', [ProductController::class, 'store']);
                Route::get('/', [ProductController::class, 'index']);
                Route::get('/is-product-exists', [ProductController::class, 'checkProductExists']);
                Route::get('/categories', [ProductController::class, 'getProductCategories']);
                Route::get('/{productId}', [ProductController::class, 'show']);
            });
        });

        // Product Measurement routes - available for authenticated users
        Route::prefix('product-measurement')->group(function () {
            Route::post('/', [ProductMeasurementController::class, 'store']);
            Route::post('/bulk', [ProductMeasurementController::class, 'bulkStore']);
            Route::post('/{productMeasurementId}/set-batch-number', [ProductMeasurementController::class, 'setBatchNumber']);
            Route::post('/{productMeasurementId}/submit', [ProductMeasurementController::class, 'submitMeasurement']);
            Route::post('/{productMeasurementId}/samples/check', [ProductMeasurementController::class, 'checkSamples']);
            Route::post('/{productMeasurementId}/save-progress', [ProductMeasurementController::class, 'saveProgress']);
            Route::post('/{productMeasurementId}/create-sample-product', [ProductMeasurementController::class, 'createSampleProduct']);
            Route::get('/{productMeasurementId}', [ProductMeasurementController::class, 'show']);
            Route::get('/', [ProductMeasurementController::class, 'index']);
        });

        // Product Measurements List - for Monthly Target page

        // Product Categories - available for authenticated users
        Route::prefix('product-categories')->group(function () {
            Route::get('/', [ProductCategoryController::class, 'index']);
            Route::get('/search-products', [ProductCategoryController::class, 'searchProducts']);
            Route::get('/structure', [ProductCategoryController::class, 'getStructure']);
            Route::get('/{categoryId}/products', [ProductCategoryController::class, 'getProducts']);
        });

        // Measurement Instruments - available for authenticated users
        Route::prefix('measurement-instruments')->group(function () {
            Route::get('/', [MeasurementInstrumentController::class, 'index']);
            Route::get('/{instrumentId}', [MeasurementInstrumentController::class, 'show']);
        });

        // Tools - available for authenticated users
        Route::prefix('tools')->group(function () {
            Route::get('/', [ToolController::class, 'index']);
            Route::get('/models', [ToolController::class, 'getModels']);
            Route::get('/by-model', [ToolController::class, 'getByModel']);
            Route::get('/{id}', [ToolController::class, 'show']);
            
            // Admin and SuperAdmin can CRUD
            Route::middleware('role:admin,superadmin')->group(function () {
                Route::post('/', [ToolController::class, 'store']);
                Route::put('/{id}', [ToolController::class, 'update']);
                Route::delete('/{id}', [ToolController::class, 'destroy']);
            });
        });

        // Issues - available for authenticated users
        Route::prefix('issues')->group(function () {
            Route::get('/', [IssueController::class, 'index']);
            Route::get('/{id}', [IssueController::class, 'show']);
            Route::get('/{id}/comments', [IssueController::class, 'getComments']);
            
            // All authenticated users can comment
            Route::post('/{id}/comments', [IssueController::class, 'addComment']);
            Route::delete('/{issueId}/comments/{commentId}', [IssueController::class, 'deleteComment']);
            
            // Admin and SuperAdmin can CRUD issues
            Route::middleware('role:admin,superadmin')->group(function () {
                Route::post('/', [IssueController::class, 'store']);
                Route::put('/{id}', [IssueController::class, 'update']);
                Route::delete('/{id}', [IssueController::class, 'destroy']);
            });
        });

        // SuperAdmin only routes
        Route::middleware('role:superadmin')->group(function () {
            Route::post('/create-user', [AuthController::class, 'createUser']);
            Route::get('/get-user-list', [AuthController::class, 'getUserList']);
            Route::delete('/delete-users', [AuthController::class, 'deleteUsers']);
        });
    });
});



