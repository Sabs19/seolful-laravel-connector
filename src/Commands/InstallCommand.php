<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Seolful\Connector\Concerns\WritesEnvFile;
use Throwable;

class InstallCommand extends Command
{
    use WritesEnvFile;

    protected $signature = 'seolful:install
        {key? : Connection key from your Seolful dashboard}
        {--nextjs-url= : Next.js revalidation endpoint URL (e.g. https://yourapp.com/api/seolful/revalidate)}
        {--nextjs-secret= : Shared secret for the revalidation webhook (auto-generated if blank)}';

    protected $description = 'Install Seolful: run migrations and connect in one step';

    public function handle(): int
    {
        $this->newLine();
        $this->components->info('Installing Seolful');
        $this->newLine();

        // Step 1 — migrations
        $this->components->task('Running migrations', function () {
            try {
                Artisan::call('migrate', [
                    '--path'  => 'vendor/seolful/laravel-connector/database/migrations',
                    '--force' => true,
                ], $this->output);
                return true;
            } catch (Throwable $e) {
                $this->newLine();
                $this->components->error('Migration failed: ' . $e->getMessage());
                return false;
            }
        });

        $this->newLine();

        // Step 2 — connect (delegates to ConnectCommand)
        $args = [];
        if ($key = trim((string) $this->argument('key'))) {
            $args['key'] = $key;
        }

        $result = $this->call('seolful:connect', $args);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        // Step 3 — optional Next.js revalidation webhook setup
        $this->configureNextJs();

        return self::SUCCESS;
    }

    private function configureNextJs(): void
    {
        $url    = $this->option('nextjs-url');
        $secret = $this->option('nextjs-secret');

        // Non-interactive with no flags — skip silently
        if ($url === null && ! $this->input->isInteractive()) {
            return;
        }

        // Interactive — ask if they use a Next.js frontend
        if ($url === null) {
            $this->newLine();
            if (! $this->confirm('Do you use a Next.js frontend?', false)) {
                return;
            }
            $url = $this->ask('Next.js revalidation URL');
        }

        if (! $url) {
            return;
        }

        // Auto-generate secret if not provided or left blank
        if ($secret === null && $this->input->isInteractive()) {
            $secret = $this->ask('Revalidation secret (leave blank to auto-generate)') ?: null;
        }
        $secret = $secret ?: Str::random(32);

        $this->newLine();
        $this->components->task('Writing Next.js config to .env', function () use ($url, $secret) {
            $this->writeEnv('SEOLFUL_REVALIDATE_URL', $url);
            $this->writeEnv('SEOLFUL_REVALIDATE_SECRET', $secret);
            return true;
        });

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Revalidation URL</>', $url);
        $this->components->twoColumnDetail('<fg=gray>Secret</>', $secret);
        $this->newLine();
        $this->line('  <fg=yellow>Add this secret to your Next.js app:</>');
        $this->line("  SEOLFUL_REVALIDATE_SECRET={$secret}");
        $this->newLine();
    }
}
