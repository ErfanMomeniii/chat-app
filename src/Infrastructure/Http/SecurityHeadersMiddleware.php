<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds security headers to every response.
 *
 * Defense-in-depth against common attack vectors:
 * - X-Content-Type-Options: prevents MIME-type sniffing
 * - X-Frame-Options: prevents clickjacking via iframes
 * - Content-Security-Policy: restricts resource loading to same origin
 * - Strict-Transport-Security: enforces HTTPS in production
 * - Cache-Control: prevents caching of API responses with user data
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'none'; frame-ancestors 'none'"
            )
            ->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            )
            ->withHeader(
                'Cache-Control',
                'no-store, no-cache, must-revalidate'
            );
    }
}
