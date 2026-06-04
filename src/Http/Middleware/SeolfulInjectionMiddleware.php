<?php

namespace Seolful\Connector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Seolful\Connector\SeolfulHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically injects Seolful-managed SEO values into HTML responses.
 *
 * Handles: <title>, <meta name="description">, and JSON-LD structured data.
 *
 * This closes the publish loop without requiring the developer to wire up
 * a SeolfulFixApplied listener or change their templates. Values are pulled
 * from seolful_seo_pages and cached (default: 5 min) per URL.
 *
 * Disable per-route: add the route to `seolful.injection.exclude_paths` in
 * config/seolful.php, or set SEOLFUL_MIDDLEWARE_ENABLED=false globally.
 */
class SeolfulInjectionMiddleware
{
    // Marks the schema block we inject so it can be idempotently replaced.
    private const SCHEMA_START = '<!-- seolful:schema:start -->';
    private const SCHEMA_END   = '<!-- seolful:schema:end -->';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldProcess($request, $response)) {
            return $response;
        }

        $page = SeolfulHelper::forUrl($request->url());

        if (! $page) {
            return $response;
        }

        $html     = $response->getContent();
        $original = $html;

        if ($page->title) {
            $html = $this->replaceTitle($html, $page->title);
        }

        if ($page->meta_description) {
            $html = $this->replaceOrInjectMeta($html, $page->meta_description);
        }

        if (! empty($page->structured_data)) {
            $html = $this->injectSchema($html, $page->structured_data);
        }

        if ($page->demote_h1) {
            $html = $this->demoteSecondaryH1s($html);
        }

        if ($html !== $original) {
            $response->setContent($html);
        }

        return $response;
    }

    // -------------------------------------------------------------------------

    private function shouldProcess(Request $request, Response $response): bool
    {
        if (! config('seolful.injection.middleware', true)) {
            return false;
        }

        // Only GET requests produce cacheable, indexable pages.
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Only work on real Response objects (not StreamedResponse, BinaryFileResponse, etc.)
        if (! $response instanceof \Illuminate\Http\Response) {
            return false;
        }

        // Only HTML content.
        $ct = $response->headers->get('Content-Type', '');
        if (! str_contains($ct, 'text/html')) {
            return false;
        }

        // Honour path exclusions from config.
        $excludes = config('seolful.injection.exclude_paths', []);
        $path     = $request->path();
        foreach ($excludes as $pattern) {
            if (fnmatch(ltrim($pattern, '/'), $path)) {
                return false;
            }
        }

        return true;
    }

    private function replaceTitle(string $html, string $title): string
    {
        $safe   = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $result = preg_replace(
            '/<title[^>]*>[^<]*<\/title>/i',
            "<title>{$safe}</title>",
            $html,
            1
        );

        return $result ?? $html;
    }

    private function replaceOrInjectMeta(string $html, string $description): string
    {
        $safe        = htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $replacement = "<meta name=\"description\" content=\"{$safe}\">";

        // Replace an existing <meta name="description"> regardless of attribute order.
        $result = preg_replace(
            '/<meta\s[^>]*name=["\']description["\'][^>]*\/?>/i',
            $replacement,
            $html,
            1
        );

        if ($result === null || $result === $html) {
            // No existing tag — inject before </head>.
            return str_ireplace('</head>', $replacement . "\n</head>", $html);
        }

        return $result;
    }

    private function demoteSecondaryH1s(string $html): string
    {
        $count = 0;

        return preg_replace_callback(
            '/<h1(\s[^>]*)?>.*?<\/h1>/is',
            function (array $match) use (&$count): string {
                $count++;

                if ($count === 1) {
                    return $match[0];
                }

                $tag = preg_replace('/^<h1/i', '<h2', $match[0]);
                $tag = preg_replace('/<\/h1>$/i', '</h2>', $tag ?? $match[0]);

                return $tag ?? $match[0];
            },
            $html
        ) ?? $html;
    }

    private function injectSchema(string $html, array $schemas): string
    {
        // Remove any previously injected block so this is idempotent.
        $pattern = '/' . preg_quote(self::SCHEMA_START, '/') . '.*?' . preg_quote(self::SCHEMA_END, '/') . '/s';
        $html    = preg_replace($pattern, '', $html) ?? $html;

        $scripts = implode("\n", array_map(
            fn ($s) => '<script type="application/ld+json">'
                . json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
                . '</script>',
            $schemas
        ));

        $block = self::SCHEMA_START . "\n" . $scripts . "\n" . self::SCHEMA_END;

        return str_ireplace('</head>', $block . "\n</head>", $html);
    }
}
