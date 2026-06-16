<?php

namespace Seolful\Connector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateNextJsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('seolful.nextjs.token', '');

        if ($configured === '') {
            return response()->json(['error' => 'Next.js token not configured — run php artisan seolful:install --nextjs-path=...'], 403);
        }

        if (! hash_equals($configured, (string) $request->header('X-Seolful-Nextjs-Token', ''))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
