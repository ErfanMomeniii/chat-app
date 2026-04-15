<?php

declare(strict_types=1);

namespace Tests\Action\Member;

use Tests\TestCase;

final class LeaveGroupActionTest extends TestCase
{
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupId = $this->createGroup('test-group', 'alice');
    }

    public function testLeaveGroupReturns204WithEmptyBody(): void
    {
        $this->joinGroup($this->groupId, 'bob');

        $response = $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    public function testLeaveGroupRemovesMembership(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );
        $userIds = array_column($body['data'], 'user_id');
        $this->assertNotContains('bob', $userIds);
        $this->assertCount(1, $body['data']); // only alice remains
    }

    public function testLeaveGroupWhenNotMemberReturns403(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'stranger')
            ),
            403,
            'forbidden',
        );
    }

    public function testLeaveNonexistentGroupReturns404(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('DELETE', '/groups/999/members')),
            404,
            'not_found',
        );
    }

    public function testCannotSendMessageAfterLeaving(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['content' => 'Hello'], 'bob')
            ),
            403,
            'forbidden',
        );
    }

    public function testCannotReadMessagesAfterLeaving(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'bob')
            ),
            403,
            'forbidden',
        );
    }

    public function testCanRejoinAfterLeaving(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/members", null, 'bob')
            ),
            201,
        );

        // Bob can send messages again
        $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['content' => 'I am back'], 'bob')
            ),
            201,
        );
    }

    public function testCreatorCanLeaveOwnGroup(): void
    {
        $response = $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'alice')
        );

        $this->assertSame(204, $response->getStatusCode());

        // Alice is no longer a member
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['count']);
    }

    public function testGroupPersistsAfterCreatorLeaves(): void
    {
        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'alice')
        );

        // Group still exists and is accessible
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}")),
        );
        $this->assertSame('test-group', $body['name']);
    }

    public function testMessagesPreservedAfterSenderLeaves(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->sendMessage($this->groupId, 'Before leaving', 'bob');

        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        // Alice can still see Bob's message
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('Before leaving', $body['data'][0]['content']);
        $this->assertSame('bob', $body['data'][0]['user_id']);
    }
}
