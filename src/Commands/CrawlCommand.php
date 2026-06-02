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
            $this->error('Not connected to Seolful. Run php artisan seolful:connect first.');
            return self::FAILURE;
        }

        $this->info('Discovering pages...');

        $bar = null;

        $result = $crawler->crawl(function (string $url, int $done, int $total) use (&$bar) {
            if ($bar === null) {
                /** @var ProgressBar $bar */
                $bar = $this->output->createProgressBar($total);
                $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
                $bar->start();
            }
            $bar->setMessage(parse_url($url, PHP_URL_PATH) ?: $url);
            $bar->advance();
        });

        if ($bar instanceof ProgressBar) {
            $bar->finish();
            $this->newLine(2);
        }

        $this->info("Done. Indexed: {$result['crawled']} pages, failed: {$result['failed']}.");

        return self::SUCCESS;
    }
}
