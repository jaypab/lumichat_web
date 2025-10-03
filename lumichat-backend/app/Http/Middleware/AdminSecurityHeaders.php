<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSecurityHeaders
{
    /**
     * Add strict security headers for all admin routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Don’t leak full referrer URLs
        $response->headers->set('Referrer-Policy', 'no-referrer');
        // Disable browser features not needed in admin
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        // Basic Content Security Policy (tweak if you use inline scripts/styles)
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'"
        );

        return $response;
    }
}
