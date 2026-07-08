<?php

namespace Seolful\Connector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Seolful\Connector\Events\SeolfulFixApplied;
use Seolful\Connector\Models\SeoPage;
use Seolful\Connector\SeolfulHelper;
use Seolful\Connector\Services\WebhookDispatchService;

class UpdateSeoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id'          => 'required|integer',
            'meta_title'       => 'sometimes|string|max:255',
            'meta_description' => 'sometimes|string|max:500',
            'image_src'        => 'sometimes|string',
            'image_alt'        => 'sometimes|string',
        ]);

        $page    = SeoPage::findOrFail($data['post_id']);
        $updated = [];

        if (isset($data['meta_title'])) {
            $page->title = self::stripLabelPrefix($data['meta_title']);
            $updated[]   = 'meta_title';
        }

        if (isset($data['meta_description'])) {
            $page->meta_description = self::stripLabelPrefix($data['meta_description']);
            $updated[]              = 'meta_description';
        }

        if (isset($data['image_src'], $data['image_alt'])) {
            $alts = $page->image_alts ?? [];
            $found = false;
            foreach ($alts as &$img) {
                if (($img['src'] ?? null) === $data['image_src']) {
                    $img['alt']     = $data['image_alt'];
                    $img['missing'] = false;
                    $found          = true;
                    break;
                }
            }
            unset($img);

            if (! $found) {
                // The last crawl snapshot didn't have this image yet (added since, or a
                // CDN/resize variant of a known src) — still apply the fix instead of
                // silently reporting success while changing nothing.
                $alts[] = ['src' => $data['image_src'], 'alt' => $data['image_alt'], 'missing' => false];
            }

            $page->image_alts = $alts;
            $updated[]        = 'image_alt';
        }

        $page->save();
        SeolfulHelper::forgetUrl($page->url);

        event(new SeolfulFixApplied($page, $updated, $data));

        app(WebhookDispatchService::class)->dispatch($page->url);

        return response()->json([
            'status'         => 'success',
            'fields_updated' => $updated,
        ]);
    }

    private static function stripLabelPrefix(string $value): string
    {
        $prefixes = [
            'Meta Description:', 'Meta description:',
            'Title Tag:', 'Title tag:', 'Title:',
            'New Title:', 'New Meta Description:',
            'Revised Title:', 'Revised Meta Description:',
            'Updated Title:', 'Updated Meta Description:',
            'H1:', 'H1 Heading:', 'Alt Text:', 'Alt text:',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return trim(substr($value, strlen($prefix)));
            }
        }

        return $value;
    }

    public function updateAiVisibility(Request $request): JsonResponse
    {
        $data = $request->validate([
            'post_id'          => 'sometimes|integer',
            'llms_txt_content' => 'sometimes|string',
            'schema_jsonld'    => 'sometimes|array',
        ]);

        $updated = [];

        if (isset($data['llms_txt_content'])) {
            file_put_contents(public_path('llms.txt'), $data['llms_txt_content']);
            $updated[] = 'llms_txt_content';
        }

        if (isset($data['schema_jsonld'], $data['post_id'])) {
            $page                  = SeoPage::findOrFail($data['post_id']);
            $page->structured_data = $data['schema_jsonld'];
            $page->save();
            SeolfulHelper::forgetUrl($page->url);
            $updated[] = 'schema_jsonld';

            event(new SeolfulFixApplied($page, $updated, $data));

            app(WebhookDispatchService::class)->dispatch($page->url);
        }

        return response()->json([
            'status'         => 'success',
            'fields_updated' => $updated,
        ]);
    }
}
