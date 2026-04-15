<?php

declare(strict_types=1);

namespace Tests\Action\Group;

use Tests\TestCase;

final class DeleteGroupActionTest extends TestCase
{
    public function testDeleteGroup(): void
    {
        $groupId = $this->createGroup('doomed', 'alice');

        $response = $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$groupId}", null, 'alice')
        );

        $this->assertSame(204, $response->getStatusCode());

        // Verify it's gone
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}", null, 'alice')
            ),
            404,
            'not_found',
        );
    }

    public function testDeleteGroupForbiddenForNonCreator(): void
    {
        $groupId = $this->createGroup('protected', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('DELETE', "/groups/{$groupId}", null, 'bob')
            ),
            403,
            'forbidden',
        );

        // Verify it still exists
        $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}", null, 'alice')
            ),
        );
    }

    public function testDeleteGroupNotFound(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('DELETE', '/groups/999')
            ),
            404,
            'not_found',
        );
    }

    public function testDeleteGroupCascadesMembers(): void
    {
        $groupId = $this->createGroup('cascade-test', 'alice');
        $this->joinGroup($groupId, 'bob');

        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$groupId}", null, 'alice')
        );

        // Group no longer exists
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}", null, 'alice')
            ),
            404,
            'not_found',
        );
    }

    public function testDeleteGroupCascadesMessages(): void
    {
        $groupId = $this->createGroup('msg-cascade', 'alice');
        $this->sendMessage($groupId, 'hello', 'alice');

        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$groupId}", null, 'alice')
        );

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}", null, 'alice')
            ),
            404,
            'not_found',
        );
    }

    public function testDeleteGroupRemovedFromList(): void
    {
        $this->createGroup('keep-this', 'alice');
        $groupId = $this->createGroup('delete-this', 'alice');

        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$groupId}", null, 'alice')
        );

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertCount(1, $body['data']);
        $this->assertSame('keep-this', $body['data'][0]['name']);
    }

    public function testDeleteGroupIdempotentSecondCallReturns404(): void
    {
        $groupId = $this->createGroup('once', 'alice');

        $response = $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$groupId}", null, 'alice')
        );
        $this->assertSame(204, $response->getStatusCode());

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('DELETE', "/groups/{$groupId}", null, 'alice')
            ),
            404,
            'not_found',
        );
    }
}
