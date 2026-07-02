<?php

namespace Seolful\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Seolful\Connector\Services\SiteCrawlerService;

class CrawlController extends Controller
{
    public function store(SiteCrawlerService $crawler): JsonResponse
    {
        $result = $crawler->crawl();

        return response()->json([
            'status'           => 'success',
            'crawled'          => $result['crawled'],
            'failed'           => $result['failed'],
            'total'            => $result['total'],
            'discovery_method' => $result['discovery_method'] ?? null,
        ]);
    }
}
