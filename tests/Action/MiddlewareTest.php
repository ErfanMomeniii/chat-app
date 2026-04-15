<?php

declare(strict_types=1);

namespace Tests\Action;

use Tests\TestCase;

final class MiddlewareTest extends TestCase
{
    // --- User Identity Middleware ---

    public function testMissingUserIdReturns401(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups', userId: '')),
            401,
            'unauthorized',
        );
    }

    public function testInvalidUserIdWithSpecialCharsReturns400(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups', userId: 'invalid user!@#')),
            400,
            'bad_request',
        );
    }

    public function testUserIdWithSpacesReturns400(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups', userId: 'user name')),
            400,
            'bad_request',
        );
    }

    public function testUserIdWithDotsReturns400(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups', userId: 'user.name')),
            400,
            'bad_request',
        );
    }

    public function testUserIdTooLongReturns400(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups', userId: str_repeat('a', 65))),
            400,
            'bad_request',
        );
    }

    public function testValidUserIdFormats(): void
    {
        foreach (['alice', 'user-123', 'user_456', 'A', 'z', str_repeat('x', 64)] as $userId) {
            $body = $this->assertJsonResponse(
                $this->app->handle($this->createRequest('GET', '/groups', userId: $userId)),
            );
            $this->assertArrayHasKey('data', $body, "User ID '{$userId}' should be valid");
        }
    }

    public function testUserIdWithOnlyDashes(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups', userId: '---')),
        );
        $this->assertArrayHasKey('data', $body);
    }

    public function testUserIdWithOnlyUnderscores(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups', userId: '___')),
        );
        $this->assertArrayHasKey('data', $body);
    }

    public function testUserIdWithNumbers(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups', userId: '12345')),
        );
        $this->assertArrayHasKey('data', $body);
    }

    public function testUserIdStoredCorrectlyInCreatedGroup(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'test'], 'my-user-42')),
            201,
        );
        $this->assertSame('my-user-42', $body['created_by']);
    }

    // --- Error Handler / Routing ---

    public function testNotFoundReturnsJson(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/nonexistent-route')),
            404,
            'not_found',
        );
    }

    public function testMethodNotAllowedReturnsJson(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('PATCH', '/groups')),
            405,
            'method_not_allowed',
        );
    }

    public function testPutMethodNotAllowed(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('PUT', '/groups')),
            405,
            'method_not_allowed',
        );
    }

    public function testDeleteOnGroupsNotAllowed(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('DELETE', '/groups')),
            405,
            'method_not_allowed',
        );
    }

    // --- Group ID Route Parameter ---

    public function testNonNumericGroupIdReturns404(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups/abc')),
            404,
            'not_found',
        );
    }

    public function testGroupIdZeroReturns404(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups/0')),
            404,
            'not_found',
        );
    }

    public function testNegativeGroupIdReturns404(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups/-1')),
            404,
            'not_found',
        );
    }

    // --- Security Headers ---

    public function testSecurityHeadersPresent(): void
    {
        $response = $this->app->handle(
            $this->createRequest('GET', '/groups')
        );

        $this->assertSame(
            'nosniff',
            $response->getHeaderLine('X-Content-Type-Options')
        );
        $this->assertSame(
            'DENY',
            $response->getHeaderLine('X-Frame-Options')
        );
        $this->assertStringContainsString(
            "default-src 'none'",
            $response->getHeaderLine('Content-Security-Policy')
        );
        $this->assertStringContainsString(
            'max-age=',
            $response->getHeaderLine('Strict-Transport-Security')
        );
        $this->assertStringContainsString(
            'no-store',
            $response->getHeaderLine('Cache-Control')
        );
    }

    public function testSecurityHeadersOnHealthEndpoint(): void
    {
        $response = $this->app->handle(
            $this->createRequest('GET', '/health', null, '')
        );

        $this->assertSame(
            'nosniff',
            $response->getHeaderLine('X-Content-Type-Options')
        );
    }

    // --- Correlation ID ---

    public function testResponseIncludesRequestIdHeader(): void
    {
        $response = $this->app->handle(
            $this->createRequest('GET', '/groups')
        );

        $requestId = $response->getHeaderLine('X-Request-Id');
        $this->assertNotEmpty($requestId);
        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $requestId
        );
    }

    public function testClientProvidedRequestIdIsEchoed(): void
    {
        $request = $this->createRequest('GET', '/groups');
        $request = $request->withHeader('X-Request-Id', 'client-trace-123');

        $response = $this->app->handle($request);

        $this->assertSame(
            'client-trace-123',
            $response->getHeaderLine('X-Request-Id')
        );
    }

    public function testInvalidRequestIdIsReplaced(): void
    {
        $request = $this->createRequest('GET', '/groups');
        $request = $request->withHeader('X-Request-Id', 'invalid id with spaces!@#');

        $response = $this->app->handle($request);

        $requestId = $response->getHeaderLine('X-Request-Id');
        $this->assertNotSame('invalid id with spaces!@#', $requestId);
        $this->assertNotEmpty($requestId);
    }

    public function testHealthEndpointIncludesRequestId(): void
    {
        $response = $this->app->handle(
            $this->createRequest('GET', '/health', null, '')
        );

        $this->assertNotEmpty(
            $response->getHeaderLine('X-Request-Id')
        );
    }

    // --- Content-Type on all error responses ---

    public function testAllErrorResponsesHaveJsonContentType(): void
    {
        $errorRequests = [
            $this->createRequest('GET', '/groups', userId: ''),           // 401
            $this->createRequest('GET', '/groups', userId: 'bad!'),       // 400
            $this->createRequest('GET', '/nonexistent'),                  // 404
            $this->createRequest('PATCH', '/groups'),                     // 405
        ];

        foreach ($errorRequests as $request) {
            $response = $this->app->handle($request);
            $this->assertSame(
                'application/json',
                $response->getHeaderLine('Content-Type'),
                "Expected JSON Content-Type for status {$response->getStatusCode()}"
            );
        }
    }
}
