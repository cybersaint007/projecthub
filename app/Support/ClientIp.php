<?php

namespace App\Support;

use Illuminate\Http\Request;

class ClientIp
{
    public static function resolve(Request $request): string
    {
        // Cloudflare's connecting IP header takes priority
        if ($cf = $request->header('CF-Connecting-IP')) {
            return $cf;
        }

        // Fall back to Laravel's resolved client IP (respects TrustProxies)
        return $request->ip() ?? '127.0.0.1';
    }
}
