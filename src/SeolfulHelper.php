<?php

namespace Seolful\Connector;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Seolful\Connector\Models\SeoPage;

/**
 * Static helpers used by both the injection middleware and the Blade directives.
 *
 * All DB lookups are cached per URL to avoid a query on every request.
 */
class SeolfulHelper
{
    public static function cacheKey(string $url): string
    {
        return 'seolful_page_' . md5(rtrim($url, '/'));
    }

    public static function forUrl(string $url): ?SeoPage
    {
        $normalised = rtrim($url, '/');
        $ttl        = (int) config('seolful.injection.cache_ttl', 300);

        $result = Cache::remember(self::cacheKey($normalised), $ttl, function () use ($normalised) {
            return SeoPage::where('url', $normalised)
                ->orWhere('url', $normalised . '/')
                ->first()
                // Cache a sentinel so a miss is not re-queried every request.
                ?? false;
        });

        $dbPage = $result instanceof SeoPage ? $result : null;

        return self::applyFileOverrides($dbPage, $normalised);
    }

    /**
     * Layers seolful.overrides.json (committed via the GitHub PR publish flow)
     * on top of whatever the DB/live-write path already has, keyed by URL path.
     * File values win field-by-field when present; everything else falls back
     * to the DB row. Lets a site take either publish path, or both, per fix.
     */
    private static function applyFileOverrides(?SeoPage $dbPage, string $url): ?SeoPage
    {
        $path     = parse_url($url, PHP_URL_PATH) ?: '/';
        $override = self::fileOverrides()[$path] ?? null;

        if (! $override) {
            return $dbPage;
        }

        $merged = $dbPage ? clone $dbPage : new SeoPage(['url' => $url]);

        if (isset($override['title'])) {
            $merged->title = $override['title'];
        }
        if (isset($override['metaDescription'])) {
            $merged->meta_description = $override['metaDescription'];
        }
        if (isset($override['structuredData'])) {
            $merged->structured_data = $override['structuredData'];
        }
        if (array_key_exists('demoteH1', $override)) {
            $merged->demote_h1 = (bool) $override['demoteH1'];
        }
        if (isset($override['imageAlts'])) {
            $merged->image_alts = $override['imageAlts'];
        }

        return $merged;
    }

    /** @return array<string, array<string, mixed>> keyed by URL path */
    private static function fileOverrides(): array
    {
        return Cache::remember('seolful_file_overrides', 60, function () {
            $path = base_path('seolful.overrides.json');

            if (! is_file($path)) {
                return [];
            }

            $decoded = json_decode(file_get_contents($path), true);

            return is_array($decoded) ? $decoded : [];
        });
    }

    public static function current(): ?SeoPage
    {
        return self::forUrl(Request::url());
    }

    /** Return the Seolful-managed title for the current URL, or $fallback. */
    public static function title(string $fallback = ''): string
    {
        return self::current()?->title ?? $fallback;
    }

    /** Return the Seolful-managed meta description for the current URL, or $fallback. */
    public static function metaDescription(string $fallback = ''): string
    {
        return self::current()?->meta_description ?? $fallback;
    }

    /** Return <script type="application/ld+json"> tags for the current URL (safe HTML string). */
    public static function schemaScripts(): string
    {
        $schemas = self::current()?->structured_data ?? [];

        if (empty($schemas)) {
            return '';
        }

        return implode("\n", array_map(
            fn ($s) => '<script type="application/ld+json">'
                . json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
                . '</script>',
            $schemas
        ));
    }

    /** Bust the cache for a specific URL — called after a fix is published. */
    public static function forgetUrl(string $url): void
    {
        Cache::forget(self::cacheKey($url));
    }
}
