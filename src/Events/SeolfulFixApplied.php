<?php

namespace Seolful\Connector\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Seolful\Connector\Models\SeoPage;

/**
 * Fired after Seolful writes a fix back to seolful_seo_pages.
 *
 * Listen to this event in your app's EventServiceProvider to sync the
 * updated values into your own SEO package (e.g. spatie/laravel-seo,
 * artesaos/seotools) or your site's content models.
 *
 * Example:
 *   protected $listen = [
 *       \Seolful\Connector\Events\SeolfulFixApplied::class => [
 *           \App\Listeners\SyncSeolfulFix::class,
 *       ],
 *   ];
 */
class SeolfulFixApplied
{
    use Dispatchable;

    public function __construct(
        public readonly SeoPage $page,
        public readonly array $updatedFields,
        public readonly array $rawPayload,
    ) {}
}
