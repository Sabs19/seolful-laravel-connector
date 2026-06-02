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

        return $result instanceof SeoPage ? $result : null;
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
