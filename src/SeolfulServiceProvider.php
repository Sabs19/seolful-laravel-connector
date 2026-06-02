<?php

namespace Seolful\Connector;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Seolful\Connector\Commands\ConnectCommand;
use Seolful\Connector\Commands\CrawlCommand;
use Seolful\Connector\Http\Middleware\SeolfulInjectionMiddleware;
use Seolful\Connector\Services\SiteCrawlerService;

class SeolfulServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        $this->publishes([
            __DIR__ . '/../config/seolful.php' => config_path('seolful.php'),
        ], 'seolful-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConnectCommand::class,
                CrawlCommand::class,
            ]);
        }

        $this->registerInjectionMiddleware();
        $this->registerBladeDirectives();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/seolful.php', 'seolful');

        $this->app->singleton(SiteCrawlerService::class);
    }

    // -------------------------------------------------------------------------

    private function registerInjectionMiddleware(): void
    {
        if (! config('seolful.injection.middleware', true)) {
            return;
        }

        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', SeolfulInjectionMiddleware::class);
    }

    private function registerBladeDirectives(): void
    {
        /*
         * @seolful_title('fallback text')
         *
         * Outputs the Seolful-managed title for the current URL.
         * Falls back to the given expression if no override exists.
         *
         * Usage:
         *   <title>@seolful_title(config('app.name'))</title>
         *   <title>@seolful_title($post->title . ' | ' . config('app.name'))</title>
         */
        Blade::directive('seolful_title', function (string $expression): string {
            $fallback = $expression !== '' ? $expression : "''";
            return "<?php echo e(\\Seolful\\Connector\\SeolfulHelper::title({$fallback})); ?>";
        });

        /*
         * @seolful_meta('fallback description')
         *
         * Outputs the Seolful-managed meta description for the current URL.
         *
         * Usage:
         *   <meta name="description" content="@seolful_meta($post->excerpt)">
         */
        Blade::directive('seolful_meta', function (string $expression): string {
            $fallback = $expression !== '' ? $expression : "''";
            return "<?php echo e(\\Seolful\\Connector\\SeolfulHelper::metaDescription({$fallback})); ?>";
        });

        /*
         * @seolful_schema
         *
         * Outputs <script type="application/ld+json"> tags for the current URL.
         * Safe to include even when no schema exists — outputs nothing in that case.
         *
         * Usage (place inside <head>):
         *   @seolful_schema
         */
        Blade::directive('seolful_schema', function (): string {
            return "<?php echo \\Seolful\\Connector\\SeolfulHelper::schemaScripts(); ?>";
        });
    }
}
