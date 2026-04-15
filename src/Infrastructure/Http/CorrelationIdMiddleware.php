<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Assigns a unique correlation ID to each request for distributed tracing.
 *
 * Reads an existing X-Request-Id header from the client (e.g. load balancer)
 * or generates a new UUID v4. The ID is stored as a request attribute and
 * echoed back in the response header.
 */
final class CorrelationIdMiddleware implements MiddlewareInterface
{
    public const HEADER = 'X-Request-Id';
    public const ATTRIBUTE = 'requestId';

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $requestId = $request->getHeaderLine(self::HEADER);

        if ($requestId === '' || !$this->isValidId($requestId)) {
            $requestId = $this->generateId();
        }

        $request = $request->withAttribute(self::ATTRIBUTE, $requestId);
        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER, $requestId);
    }

    private function isValidId(string $id): bool
    {
        return preg_match('/^[\w\-]{1,128}$/', $id) === 1;
    }

    private function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }
}
