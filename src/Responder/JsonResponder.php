<?php

declare(strict_types=1);

namespace App\Responder;

use Psr\Http\Message\ResponseInterface;

final class JsonResponder
{
    public function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function empty(ResponseInterface $response, int $status = 204): ResponseInterface
    {
        return $response->withStatus($status);
    }
}
