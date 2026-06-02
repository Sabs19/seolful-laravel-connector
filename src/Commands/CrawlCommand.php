<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Seolful\Connector\Models\SeolfulConnection;
use Seolful\Connector\Services\SiteCrawlerService;
use Symfony\Component\Console\Helper\ProgressBar;

class CrawlCommand extends Command
{
    protected $signature = 'seolful:crawl';

    protected $description = 'Crawl this site and index pages for Seolful SEO auditing';

    public function handle(SiteCrawlerService $crawler): int
    {
        if (SeolfulConnection::count() === 0) {
            $this->newLine();
            $this->components->error('Not connected to Seolful.');
            $this->newLine();
            $this->line('  Run <fg=yellow>php artisan seolful:connect</> first.');
            $this->newLine();
            return self::FAILURE;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        // Verify the app is reachable before crawling. The crawler makes HTTP
        // requests to every page, so the site must be running and accessible.
        $this->newLine();
        $reachable = false;

        $this->components->task("Checking connectivity to {$appUrl}", function () use ($appUrl, &$reachable) {
            try {
                $response = Http::timeout(8)->get($appUrl);
                $reachable = $response->status() < 500;
                return $reachable;
            } catch (\Throwable) {
                return false;
            }
        });

        if (! $reachable) {
            $this->newLine();
            $this->components->error("Cannot reach {$appUrl}");
            $this->newLine();
            $this->line('  The crawler makes HTTP requests to each page, so your app must be');
            $this->line('  running and accessible at <fg=yellow>APP_URL</> when this command runs.');
            $this->newLine();
            $this->line('  Common fixes:');
            $this->line('  <fg=gray>·</> Start your local server: <fg=yellow>php artisan serve</>');
            $this->line('  <fg=gray>·</> Or set APP_URL to your public domain in <fg=yellow>.env</>');
            $this->newLine();
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Discovering pages…');
        $this->newLine();

        /** @var ProgressBar|null $bar */
        $bar = null;

        $result = $crawler->crawl(function (string $url, int $done, int $total) use (&$bar) {
            if ($bar === null) {
                $this->line('  Found <fg=yellow>' . $total . '</> page' . ($total === 1 ? '' : 's') . '.');
                $this->newLine();

                $bar = $this->output->createProgressBar($total);
                $bar->setFormat('  <fg=gray>%current%/%max%</> <fg=blue>[%bar%]</> %percent:3s%%  <fg=gray>%message%</>');
                $bar->setBarCharacter('<fg=blue>━</>');
                $bar->setEmptyBarCharacter('<fg=gray>─</>');
                $bar->setProgressCharacter('<fg=blue>▶</>');
                $bar->start();
            }

            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            $bar->setMessage($path);
            $bar->advance();
        });

        if ($bar instanceof ProgressBar) {
            $bar->setMessage('done');
            $bar->finish();
            $this->newLine(2);
        } else {
            $this->components->warn('No pages found. Check your sitemap URL or add URLs to seolful.crawl.urls in config/seolful.php.');
            $this->newLine();
            return self::SUCCESS;
        }

        // All pages failed — surface the actual error instead of sending to logs.
        if ($result['crawled'] === 0 && $result['failed'] > 0) {
            $this->components->error('All pages failed to crawl.');
            $this->newLine();
            if ($result['first_error'] ?? null) {
                $this->line('  <fg=gray>Error:</> ' . $result['first_error']);
                $this->newLine();
            }
            $this->line('  Make sure <fg=yellow>APP_URL</> in your .env matches the address');
            $this->line('  where your app is actually being served.');
            $this->newLine();
            return self::FAILURE;
        }

        $this->components->info('Crawl complete.');
        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Strategy</>', $result['discovery_method']);
        $this->components->twoColumnDetail('<fg=gray>Pages indexed</>', (string) $result['crawled']);

        if ($result['failed'] > 0) {
            $this->components->twoColumnDetail(
                '<fg=gray>Pages failed</>',
                '<fg=yellow>' . $result['failed'] . '</> (check storage/logs/laravel.log for details)'
            );
            if ($result['first_error'] ?? null) {
                $this->newLine();
                $this->line('  <fg=gray>First error:</> ' . $result['first_error']);
            }
        }

        $this->newLine();
        $this->line('  <fg=gray>Visit your Seolful dashboard to run an audit on this site.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
