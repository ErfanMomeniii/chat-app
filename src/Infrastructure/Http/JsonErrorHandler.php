<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Throwable;

final readonly class JsonErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private ?LoggerInterface $logger = null)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $type, $message] = match (true) {
            $exception instanceof ValidationException => [
                422, 'validation_error', $exception->getMessage(),
            ],
            $exception instanceof NotFoundException => [
                404, 'not_found', $exception->getMessage(),
            ],
            $exception instanceof ForbiddenException => [
                403, 'forbidden', $exception->getMessage(),
            ],
            $exception instanceof HttpNotFoundException => [
                404, 'not_found', 'Resource not found',
            ],
            $exception instanceof HttpMethodNotAllowedException => [
                405, 'method_not_allowed', 'Method not allowed',
            ],
            default => [
                500,
                'server_error',
                $displayErrorDetails
                    ? $exception->getMessage()
                    : 'Internal server error',
            ],
        };

        if ($logErrors && $this->logger !== null) {
            $context = [
                'request_id' => $request->getAttribute(
                    CorrelationIdMiddleware::ATTRIBUTE,
                    ''
                ),
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'status' => $status,
                'exception' => $exception::class,
            ];

            if ($logErrorDetails) {
                $context['file'] = $exception->getFile();
                $context['line'] = $exception->getLine();
                $context['trace'] = $exception->getTraceAsString();
            }

            if ($status >= 500) {
                $this->logger->error(
                    $exception->getMessage(),
                    $context
                );
            } else {
                $this->logger->notice(
                    $exception->getMessage(),
                    $context
                );
            }
        }

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
