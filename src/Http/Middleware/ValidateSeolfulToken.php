<?php

namespace Seolful\Connector\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Seolful\Connector\Models\SeolfulConnection;
use Symfony\Component\HttpFoundation\Response;

class ValidateSeolfulToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token    = $request->header('X-Seolful-Token');
        $clientId = $request->header('X-Seolful-Client-Id');

        if (! $token || ! $clientId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $connection = SeolfulConnection::where('client_id', $clientId)->first();

        if (! $connection || ! Hash::check($token, $connection->token_hash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->attributes->set('seolful_connection', $connection);

        return $next($request);
    }
}
