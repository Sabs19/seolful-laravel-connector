<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Seolful\Connector\Models\SeolfulConnection;

class ConnectCommand extends Command
{
    protected $signature = 'seolful:connect';

    protected $description = 'Connect this Laravel site to Seolful for SEO auditing';

    public function handle(): int
    {
        $appUrl  = rtrim((string) config('seolful.app_url'), '/');
        $siteUrl = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->components->info('Connecting to Seolful');
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Seolful app</>', $appUrl);
        $this->components->twoColumnDetail('<fg=gray>Site URL</>', $siteUrl);
        $this->components->twoColumnDetail('<fg=gray>Laravel</>', app()->version());
        $this->components->twoColumnDetail('<fg=gray>PHP</>', PHP_VERSION);
        $this->newLine();

        if (SeolfulConnection::count() > 0) {
            if (! $this->confirm('A connection already exists. Reconnect and issue new credentials?')) {
                return self::SUCCESS;
            }
            $this->newLine();
        }

        $clientId = Str::random(12);
        $token    = Str::random(40);

        $success = false;
        $error   = null;

        $this->components->task('Registering with Seolful', function () use ($appUrl, $clientId, $token, $siteUrl, &$success, &$error) {
            $response = Http::timeout(15)->post("{$appUrl}/api/plugin/handshake", [
                'client_id'       => $clientId,
                'token'           => $token,
                'site_url'        => $siteUrl,
                'site_name'       => config('app.name'),
                'php_version'     => PHP_VERSION,
                'platform'        => 'laravel',
                'laravel_version' => app()->version(),
            ]);

            if (! $response->successful()) {
                $error = 'HTTP ' . $response->status() . ' — ' . substr($response->body(), 0, 200);
                return false;
            }

            $success = true;
            return true;
        });

        if (! $success) {
            $this->newLine();
            $this->components->error('Handshake failed: ' . ($error ?? 'Unknown error'));
            $this->newLine();
            $this->line('  <fg=gray>Check that SEOLFUL_APP_URL is correct in your .env and the Seolful app is reachable.</>');
            $this->newLine();
            return self::FAILURE;
        }

        SeolfulConnection::query()->delete();
        SeolfulConnection::create([
            'client_id'    => $clientId,
            'token_hash'   => Hash::make($token),
            'site_url'     => $siteUrl,
            'connected_at' => now(),
        ]);

        $this->newLine();
        $this->components->info('Site connected successfully.');
        $this->newLine();
        $this->line('  <fg=gray>Next step:</> run <fg=yellow>php artisan seolful:crawl</> to index your pages.');
        $this->newLine();

        return self::SUCCESS;
    }
}
