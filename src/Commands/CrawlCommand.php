<?php

namespace Seolful\Connector\Commands;

use Illuminate\Console\Command;
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

        // The progress callback closure runs before we have $result, so we show
        // discovery method in the summary instead.
        if ($bar instanceof ProgressBar) {
            $bar->setMessage('done');
            $bar->finish();
            $this->newLine(2);
        } else {
            // No pages were found at all.
            $this->components->warn('No pages found. Check your sitemap URL or add URLs to seolful.crawl.urls in config/seolful.php.');
            $this->newLine();
            return self::SUCCESS;
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
        }

        $this->newLine();
        $this->line('  <fg=gray>Visit your Seolful dashboard to run an audit on this site.</>');
        $this->newLine();

        return self::SUCCESS;
    }
}
