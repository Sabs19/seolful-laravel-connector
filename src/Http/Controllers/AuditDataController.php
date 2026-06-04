<?php

namespace Seolful\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Seolful\Connector\Models\SeoPage;

class AuditDataController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = SeoPage::orderBy('id')->paginate(75);

        $items = $pages->getCollection()->map(function (SeoPage $page) {
            $imagesWithAlts  = $page->image_alts ?? [];
            $missingAltImages = array_values(array_filter(
                $imagesWithAlts,
                fn($img) => $img['missing'] ?? false
            ));

            return [
                'url'                => $page->url,
                'post_id'            => $page->id,
                'title'              => $page->title,
                'meta_description'   => $page->meta_description,
                'h1_in_content'      => $page->h1_count > 1,
                'h1_text'            => $page->h1_count > 1 ? $page->h1_secondary : null,
                'word_count'         => $page->word_count,
                'images_missing_alt' => array_map(fn($img) => ['src' => $img['src']], $missingAltImages),
                'internal_links'     => $page->internal_link_count,
                'structured_data'    => $page->structured_data ?? [],
                'is_noindexed'       => $page->noindex,
                'canonical_url'      => $page->canonical_url,
                'page_builder'       => null,
            ];
        });

        return response()->json([
            'data'         => $items,
            'current_page' => $pages->currentPage(),
            'last_page'    => $pages->lastPage(),
            'per_page'     => $pages->perPage(),
            'total'        => $pages->total(),
        ]);
    }
}
