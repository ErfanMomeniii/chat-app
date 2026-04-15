<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class UserIdentityMiddleware implements MiddlewareInterface
{
    /**
     * @throws \JsonException
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $userId = $request->getHeaderLine('X-User-Id');

        if ($userId === '') {
            return $this->errorResponse(401, 'unauthorized', 'Missing X-User-Id header');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $userId)) {
            return $this->errorResponse(400, 'bad_request', 'Invalid X-User-Id format');
        }

        return $handler->handle($request->withAttribute('userId', $userId));
    }

    private function errorResponse(int $status, string $type, string $message): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(
            json_encode(
                ['error' => ['type' => $type, 'message' => $message]],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            )
        );

        return $response->withHeader('Content-Type', 'application/json');
    }
}
