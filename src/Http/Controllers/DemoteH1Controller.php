<?php

namespace Seolful\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Seolful\Connector\Events\SeolfulFixApplied;
use Seolful\Connector\Models\SeoPage;
use Seolful\Connector\SeolfulHelper;
use Seolful\Connector\Services\WebhookDispatchService;

class DemoteH1Controller extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id' => 'required|integer',
        ]);

        $page           = SeoPage::findOrFail($data['post_id']);
        $page->demote_h1 = true;
        $page->save();

        SeolfulHelper::forgetUrl($page->url);

        event(new SeolfulFixApplied($page, ['h1'], $data));

        app(WebhookDispatchService::class)->dispatch($page->url);

        return response()->json([
            'status'         => 'success',
            'fields_updated' => ['h1'],
        ]);
    }
}
