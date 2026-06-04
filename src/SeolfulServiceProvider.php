<?php

namespace Seolful\Connector;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Seolful\Connector\Commands\ConnectCommand;
use Seolful\Connector\Commands\CrawlCommand;
use Seolful\Connector\Http\Middleware\SeolfulInjectionMiddleware;
use Seolful\Connector\Models\SeolfulConnection;
use Seolful\Connector\Services\SiteCrawlerService;
use Throwable;

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

            // Auto-connect when SEOLFUL_CONNECTION_KEY is set but no connection exists yet.
            // Runs only during artisan/console boot so it never slows down web requests.
            $this->app->booted(fn () => $this->attemptAutoConnect());
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

    private function attemptAutoConnect(): void
    {
        $key = (string) config('seolful.connection_key', '');
        if ($key === '') {
            return;
        }

        // Bail out if already connected. Catch DB exceptions (e.g. table not yet migrated).
        try {
            if (SeolfulConnection::exists()) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        // Back off if a recent attempt failed to avoid hammering the API.
        if (Cache::has('seolful_autoconnect_backoff')) {
            return;
        }

        try {
            $decoded = json_decode(
                base64_decode(strtr($key, '-_', '+/')),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $appUrl = rtrim((string) ($decoded['url'] ?? ''), '/');

            if ($appUrl === '') {
                return;
            }

            $clientId = Str::random(12);
            $token    = Str::random(40);
            $siteUrl  = rtrim((string) config('app.url'), '/');

            $response = Http::timeout(15)->post("{$appUrl}/api/plugin/handshake", [
                'client_id'         => $clientId,
                'token'             => $token,
                'site_url'          => $siteUrl,
                'site_name'         => config('app.name'),
                'php_version'       => PHP_VERSION,
                'platform'          => 'laravel',
                'laravel_version'   => app()->version(),
                'connection_key'    => $key,
                'connector_version' => $this->connectorVersion(),
            ]);

            if ($response->successful()) {
                SeolfulConnection::query()->delete();
                SeolfulConnection::create([
                    'client_id'    => $clientId,
                    'token_hash'   => Hash::make($token),
                    'site_url'     => $siteUrl,
                    'connected_at' => now(),
                ]);
                Log::info('Seolful: auto-connected successfully via SEOLFUL_CONNECTION_KEY.');
            } else {
                Cache::put('seolful_autoconnect_backoff', true, now()->addMinutes(5));
                Log::warning('Seolful: auto-connect failed.', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Cache::put('seolful_autoconnect_backoff', true, now()->addMinutes(5));
            Log::warning('Seolful: auto-connect failed.', ['error' => $e->getMessage()]);
        }
    }

    private function connectorVersion(): ?string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                return \Composer\InstalledVersions::getPrettyVersion('seolful/laravel-connector');
            } catch (Throwable) {}
        }
        return null;
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
