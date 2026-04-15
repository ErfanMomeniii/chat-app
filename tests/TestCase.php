<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Persistence\DatabaseFactory;
use DI\ContainerBuilder;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

abstract class TestCase extends BaseTestCase
{
    protected App $app;
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = DatabaseFactory::createAndMigrate(':memory:');

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            'settings' => fn () => [
                'db' => ['path' => ':memory:'],
                'debug' => true,
                'log_errors' => false,
            ],
            PDO::class => fn () => $this->pdo,
            LoggerInterface::class => fn () => new NullLogger(),
        ]);
        $container = $containerBuilder->build();

        $this->app = AppFactory::createFromContainer($container);

        (require dirname(__DIR__) . '/config/middleware.php')($this->app);
        (require dirname(__DIR__) . '/config/routes.php')($this->app);
    }

    protected function createRequest(
        string $method,
        string $uri,
        ?array $body = null,
        string $userId = 'test-user',
    ): ServerRequestInterface {
        $rawBody = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : '';

        return $this->buildRequest($method, $uri, $rawBody, $userId);
    }

    protected function createJsonRequest(
        string $method,
        string $uri,
        string $rawBody,
        string $userId = 'test-user',
    ): ServerRequestInterface {
        return $this->buildRequest($method, $uri, $rawBody, $userId);
    }

    protected function getResponseBody(ResponseInterface $response): array
    {
        return json_decode(
            (string) $response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    protected function createGroup(
        string $name = 'test-group',
        string $userId = 'test-user',
    ): int {
        $response = $this->app->handle(
            $this->createRequest('POST', '/groups', ['name' => $name], $userId)
        );

        return $this->getResponseBody($response)['id'];
    }

    protected function joinGroup(int $groupId, string $userId): void
    {
        $this->app->handle(
            $this->createRequest(
                'POST',
                "/groups/{$groupId}/members",
                null,
                $userId
            )
        );
    }

    protected function sendMessage(
        int $groupId,
        string $content,
        string $userId,
    ): void {
        $this->app->handle(
            $this->createRequest(
                'POST',
                "/groups/{$groupId}/messages",
                ['content' => $content],
                $userId
            )
        );
    }

    /**
     * Assert that a response is a JSON error with the expected status and type.
     */
    protected function assertJsonError(
        ResponseInterface $response,
        int $status,
        string $type,
    ): void {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame(
            'application/json',
            $response->getHeaderLine('Content-Type')
        );

        $body = $this->getResponseBody($response);
        $this->assertArrayHasKey('error', $body);
        $this->assertSame($type, $body['error']['type']);
        $this->assertIsString($body['error']['message']);
        $this->assertNotEmpty($body['error']['message']);
    }

    /**
     * Assert that a response is a successful JSON response.
     */
    protected function assertJsonResponse(
        ResponseInterface $response,
        int $status = 200,
    ): array {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame(
            'application/json',
            $response->getHeaderLine('Content-Type')
        );

        return $this->getResponseBody($response);
    }

    private function buildRequest(
        string $method,
        string $uri,
        string $rawBody,
        string $userId,
    ): ServerRequestInterface {
        $headers = new Headers();
        $headers->addHeader('Content-Type', 'application/json');

        if ($userId !== '') {
            $headers->addHeader('X-User-Id', $userId);
        }

        $stream = (new StreamFactory())->createStream($rawBody);
        $uriObj = (new UriFactory())->createUri($uri);

        return new Request($method, $uriObj, $headers, [], [], $stream);
    }
}
