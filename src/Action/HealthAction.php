<?php

declare(strict_types=1);

namespace App\Action;

use App\Responder\JsonResponder;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class HealthAction
{
    public function __construct(
        private PDO $pdo,
        private JsonResponder $responder,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        try {
            $this->pdo->query('SELECT 1');

            return $this->responder->json($response, ['status' => 'ok']);
        } catch (\Throwable $e) {
            $this->logger->critical('Health check failed: database unreachable', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->responder->json(
                $response,
                ['status' => 'error'],
                503
            );
        }
    }
}
