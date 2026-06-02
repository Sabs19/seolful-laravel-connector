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

        if (SeolfulConnection::count() > 0) {
            if (! $this->confirm('A connection already exists. Reconnect and issue new credentials?')) {
                return self::SUCCESS;
            }
        }

        $clientId = Str::random(12);
        $token    = Str::random(40);

        $this->info("Registering with Seolful at {$appUrl} ...");

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
            $this->error('Handshake failed (' . $response->status() . '): ' . $response->body());
            return self::FAILURE;
        }

        SeolfulConnection::query()->delete();
        SeolfulConnection::create([
            'client_id'    => $clientId,
            'token_hash'   => Hash::make($token),
            'site_url'     => $siteUrl,
            'connected_at' => now(),
        ]);

        $this->info('Connected successfully.');
        $this->line('Next step: run <comment>php artisan seolful:crawl</comment> to index your pages.');

        return self::SUCCESS;
    }
}
