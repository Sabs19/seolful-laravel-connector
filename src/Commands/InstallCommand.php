<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class InstallCommand extends Command
{
    protected $signature = 'seolful:install {key? : Connection key from your Seolful dashboard}';

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

        // Step 2 — connect (delegates entirely to ConnectCommand)
        $args = [];
        if ($key = trim((string) $this->argument('key'))) {
            $args['key'] = $key;
        }

        return $this->call('seolful:connect', $args);
    }
}
