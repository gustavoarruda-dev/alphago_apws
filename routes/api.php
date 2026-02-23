<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/', function () {
        return response()->json([
            'success' => true,
            'service' => 'alphago-apws',
            'version' => '1.0.0',
        ]);
    });

    Route::middleware(['internal.api', 'apws.credential'])->group(function () {
        Route::get('/status', [StatusController::class, 'status']);

        Route::prefix('sync')->group(function () {
            Route::post('/run', [SyncController::class, 'run']);
            Route::get('/runs', [SyncController::class, 'index']);
            Route::get('/cursor', [SyncController::class, 'cursor']);
        });

        Route::get('/filters', [FilterController::class, 'index']);

        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::get('/campaigns/{creativeCode}', [CampaignController::class, 'show']);
        Route::get('/media/{creativeCode}', [MediaController::class, 'show']);

        Route::prefix('analytics')->group(function () {
            Route::get('/summary', [AnalyticsController::class, 'summary']);
            Route::get('/brands', [AnalyticsController::class, 'brands']);
            Route::get('/products', [AnalyticsController::class, 'products']);
            Route::get('/regions', [AnalyticsController::class, 'regions']);
            Route::get('/media', [AnalyticsController::class, 'media']);
            Route::get('/timeline', [AnalyticsController::class, 'timeline']);
        });
    });
});
