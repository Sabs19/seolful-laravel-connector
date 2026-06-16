<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Seolful\Connector\Concerns\WritesEnvFile;
use Seolful\Connector\Models\SeolfulConnection;
use Throwable;

class ConnectCommand extends Command
{
    use WritesEnvFile;
    protected $signature = 'seolful:connect {key? : Connection key from your Seolful dashboard}';

    protected $description = 'Connect this Laravel site to Seolful for SEO auditing';

    public function handle(): int
    {
        $connectionKey = $this->resolveConnectionKey();
        $siteUrl       = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->components->info('Connecting to Seolful');
        $this->newLine();

        if ($connectionKey === '') {
            $this->newLine();
            $this->components->error('A connection key is required. Copy it from your Seolful dashboard → Site Settings → Laravel tab.');
            $this->newLine();
            return self::FAILURE;
        }

        // Prefer SEOLFUL_APP_URL if explicitly set in .env (backward compat for existing installs).
        // For new installs the URL is decoded from the connection key — no separate env var needed.
        $appUrl = rtrim((string) env('SEOLFUL_APP_URL', ''), '/');
        if ($appUrl === '' || $appUrl === 'https://app.seolful.com') {
            $appUrl = $this->extractAppUrl($connectionKey);
        }

        if ($appUrl === '') {
            $this->newLine();
            $this->components->error('The connection key appears to be invalid. Copy a fresh one from your Seolful dashboard.');
            $this->newLine();
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Seolful app</>', $appUrl);
        $this->components->twoColumnDetail('<fg=gray>Site URL</>', $siteUrl);
        $this->components->twoColumnDetail('<fg=gray>Laravel</>', app()->version());
        $this->components->twoColumnDetail('<fg=gray>PHP</>', PHP_VERSION);
        $this->newLine();

        if (SeolfulConnection::count() > 0) {
            if ($this->input->isInteractive()) {
                if (! $this->confirm('A connection already exists. Reconnect and issue new credentials?')) {
                    return self::SUCCESS;
                }
                $this->newLine();
            }
        }

        $clientId = Str::random(12);
        $token    = Str::random(40);

        $success = false;
        $error   = null;

        $this->components->task('Registering with Seolful', function () use ($appUrl, $clientId, $token, $siteUrl, $connectionKey, &$success, &$error) {
            $response = Http::timeout(15)->post("{$appUrl}/api/plugin/handshake", [
                'client_id'         => $clientId,
                'token'             => $token,
                'site_url'          => $siteUrl,
                'site_name'         => config('app.name'),
                'php_version'       => PHP_VERSION,
                'platform'          => 'laravel',
                'laravel_version'   => app()->version(),
                'connection_key'    => $connectionKey,
                'connector_version' => $this->connectorVersion(),
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
            $this->line('  <fg=gray>Check that your connection key is correct and has not expired.</>');
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
        $this->line('  <fg=gray>Next step:</> sync your site from the Seolful dashboard — the first sync will crawl and index your pages automatically.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function resolveConnectionKey(): string
    {
        $fromArg = trim((string) $this->argument('key'));

        if ($fromArg !== '') {
            $this->writeEnv('SEOLFUL_CONNECTION_KEY', $fromArg);
            return $fromArg;
        }

        return (string) config('seolful.connection_key', '');
    }

    private function extractAppUrl(string $connectionKey): string
    {
        try {
            $decoded = json_decode(
                base64_decode(strtr($connectionKey, '-_', '+/')),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            return rtrim((string) ($decoded['url'] ?? ''), '/');
        } catch (Throwable) {
            return '';
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

}
