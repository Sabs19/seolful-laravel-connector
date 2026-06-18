<?php

use Illuminate\Support\Facades\Route;
use Seolful\Connector\Http\Controllers\AuditDataController;
use Seolful\Connector\Http\Controllers\CrawlController;
use Seolful\Connector\Http\Controllers\DemoteH1Controller;
use Seolful\Connector\Http\Controllers\PageSeoController;
use Seolful\Connector\Http\Controllers\UpdateSeoController;
use Seolful\Connector\Http\Middleware\NoCacheHeaders;
use Seolful\Connector\Http\Middleware\ValidateNextJsToken;
use Seolful\Connector\Http\Middleware\ValidateSeolfulToken;

$prefix = config('seolful.api_prefix', 'api/seolful/v1');

// Seolful SaaS — write endpoints (token shared with the SaaS app)
Route::prefix($prefix)
    ->middleware(['api', ValidateSeolfulToken::class, NoCacheHeaders::class])
    ->group(function () {
        Route::get('audit-data', [AuditDataController::class, 'index']);
        Route::post('crawl', [CrawlController::class, 'store']);
        Route::post('update-seo', [UpdateSeoController::class, 'store']);
        Route::post('update-ai-visibility', [UpdateSeoController::class, 'updateAiVisibility']);
        Route::post('demote-h1', [DemoteH1Controller::class, 'store']);
    });

// Next.js frontend — read-only endpoint (separate token set by seolful:install)
Route::prefix($prefix)
    ->middleware(['api', ValidateNextJsToken::class])
    ->group(function () {
        Route::get('page-seo', [PageSeoController::class, 'show']);
    });
