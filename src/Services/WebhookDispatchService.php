<?php

namespace Seolful\Connector\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookDispatchService
{
    public function dispatch(string $pageUrl): void
    {
        $revalidateUrl = (string) config('seolful.webhook.revalidate_url', '');

        if ($revalidateUrl === '') {
            return;
        }

        try {
            Http::timeout(5)->post($revalidateUrl, [
                'url'    => $pageUrl,
                'secret' => config('seolful.webhook.secret', ''),
            ]);
        } catch (Throwable $e) {
            Log::warning('Seolful: revalidation webhook failed.', [
                'url'   => $pageUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
