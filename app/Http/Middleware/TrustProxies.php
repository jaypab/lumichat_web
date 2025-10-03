<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * For local dev, don't trust any proxies (null).
     * In production behind a load balancer, set to '*' or an array of proxy IPs.
     */
    protected $proxies = null; // or '*', but only in production

    /**
     * Use a portable bitmask instead of HEADER_X_FORWARDED_ALL.
     * (Works across Symfony/Laravel versions.)
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PORT;
}
