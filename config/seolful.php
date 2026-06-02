<?php

return [
    /*
     * The base URL of the Seolful SaaS app.
     * Used by seolful:connect to register this site.
     */
    'app_url' => env('SEOLFUL_APP_URL', 'https://app.seolful.com'),

    /*
     * User-specific connection key copied from the Seolful dashboard.
     * Required so Seolful knows which account to assign this site to.
     */
    'connection_key' => env('SEOLFUL_CONNECTION_KEY'),

    /*
     * URL prefix for the package's API routes exposed on this site.
     */
    'api_prefix' => 'api/seolful/v1',

    /*
     * Automatic HTML injection — rewrites <title>, <meta name="description">,
     * and JSON-LD in every HTML response without touching templates.
     *
     * Developers who prefer explicit control can disable this and use the
     * Blade directives instead: @seolful_title(), @seolful_meta(), @seolful_schema
     */
    'injection' => [
        'middleware' => env('SEOLFUL_MIDDLEWARE_ENABLED', true),

        /*
         * Seconds to cache a seolful_seo_pages lookup per URL.
         * Increase on high-traffic sites. Cache is busted immediately when
         * Seolful publishes a fix to that URL.
         */
        'cache_ttl' => (int) env('SEOLFUL_CACHE_TTL', 300),

        /*
         * URL path patterns to skip (fnmatch syntax, leading slash optional).
         * Example: ['api/*', 'admin/*', 'livewire/*']
         */
        'exclude_paths' => [
            'api/*',
            'livewire/*',
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
        ],
    ],

    'crawl' => [
        /*
         * When true, the crawler tries sitemap.xml first.
         * Set to false to skip sitemap discovery entirely.
         */
        'use_sitemap' => true,

        /*
         * Override the sitemap URL. Defaults to APP_URL/sitemap.xml.
         */
        'sitemap_url' => env('SEOLFUL_SITEMAP_URL'),

        /*
         * Explicit list of URLs to crawl. When non-empty this overrides
         * both sitemap discovery and route-based discovery.
         *
         * Example: ['https://example.com/', 'https://example.com/about']
         */
        'urls' => [],

        /*
         * Milliseconds to wait between page requests during a crawl.
         * Reduce to 0 on localhost; keep at 250–500 on production.
         */
        'delay_ms' => 300,

        /*
         * HTTP timeout in seconds for each page request.
         */
        'timeout' => 10,
    ],
];
