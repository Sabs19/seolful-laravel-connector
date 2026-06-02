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
        $appUrl        = rtrim((string) config('seolful.app_url'), '/');
        $siteUrl       = rtrim((string) config('app.url'), '/');
        $connectionKey = (string) config('seolful.connection_key', '');

        $this->newLine();
        $this->components->info('Connecting to Seolful');
        $this->newLine();

        // Prompt for missing SEOLFUL_APP_URL
        if ($appUrl === '' || $appUrl === 'https://app.seolful.com') {
            $appUrl = $this->ask('Seolful app URL (from your dashboard)');
            $appUrl = rtrim((string) $appUrl, '/');
            $this->writeEnv('SEOLFUL_APP_URL', $appUrl);
        }

        // Prompt for missing SEOLFUL_CONNECTION_KEY
        if ($connectionKey === '') {
            $connectionKey = $this->ask('Connection key (copy from your Seolful dashboard → Site Settings → Laravel)');
            $connectionKey = trim((string) $connectionKey);

            if ($connectionKey === '') {
                $this->newLine();
                $this->components->error('A connection key is required. Copy it from your Seolful dashboard → Site Settings → Laravel tab.');
                $this->newLine();
                return self::FAILURE;
            }

            $this->writeEnv('SEOLFUL_CONNECTION_KEY', $connectionKey);
        }

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

        $this->components->task('Registering with Seolful', function () use ($appUrl, $clientId, $token, $siteUrl, $connectionKey, &$success, &$error) {
            $payload = [
                'client_id'       => $clientId,
                'token'           => $token,
                'site_url'        => $siteUrl,
                'site_name'       => config('app.name'),
                'php_version'     => PHP_VERSION,
                'platform'        => 'laravel',
                'laravel_version' => app()->version(),
            ];

            if ($connectionKey !== '') {
                $payload['connection_key'] = $connectionKey;
            }

            $response = Http::timeout(15)->post("{$appUrl}/api/plugin/handshake", $payload);

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
            $this->line('  <fg=gray>Check that SEOLFUL_APP_URL and SEOLFUL_CONNECTION_KEY are correct.</>');
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

    private function writeEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        // Replace existing key or append
        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents);
        } else {
            $contents .= PHP_EOL . "{$key}={$value}";
        }

        file_put_contents($envPath, $contents);
    }
}
