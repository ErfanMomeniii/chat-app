<?php

declare(strict_types=1);

namespace Tests\Action;

use Tests\TestCase;

final class HealthActionTest extends TestCase
{
    public function testHealthReturns200(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/health', null, 'test-user')),
        );

        $this->assertSame('ok', $body['status']);
    }

    public function testHealthDoesNotRequireAuth(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/health', null, '')),
        );

        $this->assertSame('ok', $body['status']);
    }

    public function testHealthReturnsJsonContentType(): void
    {
        $response = $this->app->handle($this->createRequest('GET', '/health', null, ''));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}
