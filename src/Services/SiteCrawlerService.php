<?php

namespace Seolful\Connector\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Seolful\Connector\Models\SeoPage;

class SiteCrawlerService
{
    private string $appUrl;
    private string $discoveryMethod = 'unknown';

    public function __construct()
    {
        $this->appUrl = rtrim(config('app.url'), '/');
    }

    /**
     * Crawl the site and upsert SEO data into seolful_seo_pages.
     *
     * @param  callable|null  $onProgress  fn(string $url, int $done, int $total)
     * @return array{crawled: int, failed: int, total: int, discovery_method: string}
     */
    public function crawl(?callable $onProgress = null): array
    {
        $urls    = $this->discoverUrls();
        $total   = count($urls);
        $crawled = 0;
        $failed  = 0;

        foreach ($urls as $url) {
            try {
                $data = $this->analyzePage($url);
                SeoPage::updateOrCreate(
                    ['url' => $url],
                    array_merge($data, ['crawled_at' => now()])
                );
                $crawled++;
            } catch (\Throwable $e) {
                Log::warning("Seolful crawl failed for {$url}: " . $e->getMessage());
                $failed++;
            }

            if ($onProgress) {
                ($onProgress)($url, $crawled + $failed, $total);
            }

            $delayMs = (int) config('seolful.crawl.delay_ms', 300);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return [
            'crawled'          => $crawled,
            'failed'           => $failed,
            'total'            => $total,
            'discovery_method' => $this->discoveryMethod,
        ];
    }

    private function discoverUrls(): array
    {
        $explicit = config('seolful.crawl.urls', []);
        if (! empty($explicit)) {
            $this->discoveryMethod = 'config list';
            return $explicit;
        }

        if (config('seolful.crawl.use_sitemap', true)) {
            $sitemapUrl = config('seolful.crawl.sitemap_url') ?: $this->appUrl . '/sitemap.xml';
            $urls       = $this->parseSitemap($sitemapUrl);
            if (! empty($urls)) {
                $this->discoveryMethod = 'sitemap.xml';
                return $urls;
            }
        }

        $this->discoveryMethod = 'route list';
        return $this->discoverFromRoutes();
    }

    private function parseSitemap(string $url, int $depth = 0): array
    {
        if ($depth > 3) {
            return [];
        }

        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->ok()) {
                return [];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body());
            libxml_clear_errors();

            if ($xml === false) {
                return [];
            }

            $urls = [];

            foreach ($xml->url as $node) {
                $loc = (string) $node->loc;
                if ($loc) {
                    $urls[] = $loc;
                }
            }

            // Sitemap index — recurse into child sitemaps
            foreach ($xml->sitemap as $node) {
                $loc = (string) $node->loc;
                if ($loc) {
                    $urls = array_merge($urls, $this->parseSitemap($loc, $depth + 1));
                }
            }

            return array_unique($urls);
        } catch (\Throwable) {
            return [];
        }
    }

    private function discoverFromRoutes(): array
    {
        $urls = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            // Skip parameterised, API, debug, and auth routes
            if (
                str_contains($uri, '{') ||
                str_starts_with($uri, 'api/') ||
                str_starts_with($uri, '_') ||
                str_starts_with($uri, 'login') ||
                str_starts_with($uri, 'register') ||
                str_starts_with($uri, 'password') ||
                str_starts_with($uri, 'email') ||
                str_starts_with($uri, 'sanctum')
            ) {
                continue;
            }

            $urls[] = $this->appUrl . '/' . ltrim($uri, '/');
        }

        return array_unique($urls);
    }

    private function analyzePage(string $url): array
    {
        $timeout  = (int) config('seolful.crawl.timeout', 10);
        $response = Http::timeout($timeout)->get($url);

        if (! $response->ok()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->body(), LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        return [
            'url'                 => $url,
            'slug'                => parse_url($url, PHP_URL_PATH) ?: '/',
            'title'               => $this->extractTitle($xpath),
            'meta_description'    => $this->extractMetaDescription($xpath),
            'h1'                  => $this->extractFirstH1($xpath),
            'word_count'          => $this->countWords($xpath),
            'image_alts'          => $this->extractImageAlts($xpath),
            'internal_link_count' => $this->countInternalLinks($xpath, $url),
            'structured_data'     => $this->extractStructuredData($xpath),
            'noindex'             => $this->detectNoindex($xpath),
            'canonical_url'       => $this->extractCanonical($xpath),
        ];
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) ?: null : null;
    }

    private function extractMetaDescription(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//meta[@name="description"]/@content');
        return $nodes->length > 0 ? trim($nodes->item(0)->nodeValue) ?: null : null;
    }

    private function extractFirstH1(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//h1');
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) ?: null : null;
    }

    private function countWords(DOMXPath $xpath): int
    {
        $bodyNodes = $xpath->query('//body');
        if ($bodyNodes->length === 0) {
            return 0;
        }

        // Remove script and style text from body
        foreach ($xpath->query('//script|//style') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $text = $bodyNodes->item(0)->textContent;
        $text = preg_replace('/\s+/', ' ', $text);

        return str_word_count(trim($text));
    }

    private function extractImageAlts(DOMXPath $xpath): array
    {
        $images = [];

        foreach ($xpath->query('//img') as $img) {
            $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
            if (! $src) {
                continue;
            }

            $alt     = $img->getAttribute('alt');
            $images[] = [
                'src'     => $src,
                'alt'     => $alt,
                'missing' => ($alt === '' || $alt === null),
            ];
        }

        return $images;
    }

    private function countInternalLinks(DOMXPath $xpath, string $pageUrl): int
    {
        $host  = parse_url($pageUrl, PHP_URL_HOST) ?? '';
        $count = 0;

        foreach ($xpath->query('//a[@href]') as $a) {
            $href = $a->getAttribute('href');
            if (str_starts_with($href, '/') || str_contains($href, $host)) {
                $count++;
            }
        }

        return $count;
    }

    private function extractStructuredData(DOMXPath $xpath): array
    {
        $schemas = [];

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $decoded = json_decode($script->textContent, true);
            if ($decoded) {
                $schemas[] = $decoded;
            }
        }

        return $schemas;
    }

    private function detectNoindex(DOMXPath $xpath): bool
    {
        foreach (['//meta[@name="robots"]/@content', '//meta[@name="googlebot"]/@content'] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0 && str_contains(strtolower($nodes->item(0)->nodeValue), 'noindex')) {
                return true;
            }
        }

        return false;
    }

    private function extractCanonical(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//link[@rel="canonical"]/@href');
        return $nodes->length > 0 ? $nodes->item(0)->nodeValue : null;
    }
}
