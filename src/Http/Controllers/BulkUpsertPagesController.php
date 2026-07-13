<?php

namespace Seolful\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Seolful\Connector\Models\SeoPage;
use Seolful\Connector\SeolfulHelper;

/**
 * Receives pages crawled externally by the Seolful app (the same
 * sitemap-based crawl used for every other platform) and writes them into
 * this site's own seolful_seo_pages table — the app no longer crawls this
 * site's own routes/models via SiteCrawlerService for sync, only for the
 * standalone `seolful:crawl` CLI. Returns each page's row id so the app can
 * target future publish-fix writes at a real row instead of a placeholder.
 */
class BulkUpsertPagesController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pages'                             => 'required|array|max:500',
            'pages.*.url'                        => 'required|string',
            'pages.*.title'                       => 'nullable|string',
            'pages.*.meta_description'            => 'nullable|string',
            'pages.*.h1_in_content'               => 'sometimes|boolean',
            'pages.*.h1_text'                     => 'nullable|string',
            'pages.*.word_count'                  => 'sometimes|integer',
            'pages.*.images_missing_alt'          => 'sometimes|array',
            'pages.*.images_missing_alt.*.src'    => 'required|string',
            'pages.*.internal_links'              => 'sometimes|integer',
            'pages.*.all_links'                   => 'sometimes|array',
            'pages.*.structured_data'             => 'sometimes|array',
            'pages.*.is_noindexed'                => 'sometimes|boolean',
            'pages.*.canonical_url'               => 'nullable|string',
        ]);

        $rows = [];
        foreach ($data['pages'] as $page) {
            $imageAlts = array_map(
                fn ($img) => ['src' => $img['src'], 'alt' => '', 'missing' => true],
                $page['images_missing_alt'] ?? []
            );

            $rows[] = [
                'url'                 => $page['url'],
                'slug'                => parse_url($page['url'], PHP_URL_PATH) ?: '/',
                'title'               => $page['title'] ?? null,
                'meta_description'    => $page['meta_description'] ?? null,
                // The app only ever needs to know "more than one H1?" (duplicate-H1
                // detection) plus the secondary heading's text — it never reads the
                // primary H1 back, so there's no exact count to round-trip here.
                'h1_count'            => ! empty($page['h1_in_content']) ? 2 : 0,
                'h1_secondary'        => $page['h1_text'] ?? null,
                'word_count'          => (int) ($page['word_count'] ?? 0),
                'image_alts'          => json_encode($imageAlts),
                'internal_link_count' => (int) ($page['internal_links'] ?? 0),
                'all_links'           => json_encode(array_values($page['all_links'] ?? [])),
                'structured_data'     => json_encode($page['structured_data'] ?? []),
                'noindex'             => (bool) ($page['is_noindexed'] ?? false),
                'canonical_url'       => $page['canonical_url'] ?? null,
                'crawled_at'          => now(),
            ];
        }

        $urls = array_column($data['pages'], 'url');

        if (! empty($rows)) {
            SeoPage::upsert(
                $rows,
                ['url'],
                ['slug', 'title', 'meta_description', 'h1_count', 'h1_secondary', 'word_count', 'image_alts', 'internal_link_count', 'all_links', 'structured_data', 'noindex', 'canonical_url', 'crawled_at']
            );

            foreach ($urls as $url) {
                SeolfulHelper::forgetUrl($url);
            }
        }

        $ids = SeoPage::whereIn('url', $urls)->pluck('id', 'url');

        return response()->json([
            'status' => 'success',
            'pages'  => collect($urls)->map(fn ($url) => [
                'url'     => $url,
                'post_id' => $ids[$url] ?? null,
            ])->values(),
        ]);
    }
}
