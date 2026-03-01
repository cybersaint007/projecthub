<?php

namespace App\Http\Middleware;

use App\Models\AgentToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Missing bearer token.'], 401);
        }

        $agentToken = AgentToken::where('token', $token)->first();

        if (!$agentToken) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        if ($agentToken->isExpired()) {
            return response()->json(['error' => 'Token expired.'], 401);
        }

        $agentToken->touchLastUsed();

        $request->attributes->set('agent_token', $agentToken);

        return $next($request);
    }
}
