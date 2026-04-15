<?php

declare(strict_types=1);

namespace Tests\Action\Group;

use Tests\TestCase;

final class GetGroupActionTest extends TestCase
{
    public function testGetGroupReturnsAllFields(): void
    {
        $groupId = $this->createGroup('my-group', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}")),
        );

        $this->assertSame($groupId, $body['id']);
        $this->assertIsInt($body['id']);
        $this->assertSame('my-group', $body['name']);
        $this->assertIsString($body['description']);
        $this->assertSame('alice', $body['created_by']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['created_at']);
    }

    public function testGetGroupNotFound(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups/999')),
            404,
            'not_found',
        );
    }

    public function testGetGroupPreservesDescription(): void
    {
        $response = $this->app->handle(
            $this->createRequest('POST', '/groups', [
                'name' => 'with-desc',
                'description' => 'A test group',
            ])
        );
        $created = $this->getResponseBody($response);

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$created['id']}")),
        );

        $this->assertSame('A test group', $body['description']);
    }

    public function testGetGroupResponseHasExactKeys(): void
    {
        $groupId = $this->createGroup('key-test');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}")),
        );

        $this->assertSame(['id', 'name', 'description', 'created_by', 'created_at'], array_keys($body));
    }

    public function testGetGroupByAnyUser(): void
    {
        $groupId = $this->createGroup('public-group', 'alice');

        // Any user can view group details, even non-members
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}", null, 'stranger')),
        );

        $this->assertSame($groupId, $body['id']);
        $this->assertSame('public-group', $body['name']);
    }

    public function testGetGroupWithEmptyDescription(): void
    {
        $groupId = $this->createGroup('no-desc');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}")),
        );

        $this->assertSame('', $body['description']);
    }
}
