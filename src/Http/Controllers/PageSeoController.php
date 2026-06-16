<?php

namespace Seolful\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Seolful\Connector\Models\SeoPage;

class PageSeoController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $url = $request->query('url');

        if (! $url) {
            return response()->json(['error' => 'url parameter is required'], 422);
        }

        $normalised = rtrim($url, '/');

        $page = SeoPage::where('url', $normalised)
            ->orWhere('url', $normalised . '/')
            ->first();

        if (! $page) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found'            => true,
            'title'            => $page->title,
            'meta_description' => $page->meta_description,
            'structured_data'  => $page->structured_data ?? [],
            'demote_h1'        => $page->demote_h1,
            'image_alts'       => $page->image_alts ?? [],
        ]);
    }
}
