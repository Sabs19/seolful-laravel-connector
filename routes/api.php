<?php

use Illuminate\Support\Facades\Route;
use Seolful\Connector\Http\Controllers\AuditDataController;
use Seolful\Connector\Http\Controllers\DemoteH1Controller;
use Seolful\Connector\Http\Controllers\UpdateSeoController;
use Seolful\Connector\Http\Middleware\ValidateSeolfulToken;

Route::prefix(config('seolful.api_prefix', 'api/seolful/v1'))
    ->middleware(['api', ValidateSeolfulToken::class])
    ->group(function () {
        Route::get('audit-data', [AuditDataController::class, 'index']);
        Route::post('update-seo', [UpdateSeoController::class, 'store']);
        Route::post('update-ai-visibility', [UpdateSeoController::class, 'updateAiVisibility']);
        Route::post('demote-h1', [DemoteH1Controller::class, 'store']);
    });
