<?php

declare(strict_types=1);

namespace Tests\Action\Member;

use Tests\TestCase;

final class ListMembersActionTest extends TestCase
{
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupId = $this->createGroup('test-group', 'alice');
    }

    public function testListMembersIncludesCreator(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );

        $this->assertCount(1, $body['data']);
        $this->assertSame('alice', $body['data'][0]['user_id']);
        $this->assertSame(1, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testListMembersReturnsCorrectFieldTypes(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );

        $member = $body['data'][0];
        $this->assertIsInt($member['id']);
        $this->assertIsInt($member['group_id']);
        $this->assertSame($this->groupId, $member['group_id']);
        $this->assertIsString($member['user_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $member['joined_at']);
    }

    public function testListMembersEachMemberHasExactKeys(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );

        $this->assertSame(['id', 'group_id', 'user_id', 'joined_at'], array_keys($body['data'][0]));
    }

    public function testListMembersAfterJoining(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->joinGroup($this->groupId, 'charlie');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );

        $this->assertCount(3, $body['data']);
        $userIds = array_column($body['data'], 'user_id');
        $this->assertContains('alice', $userIds);
        $this->assertContains('bob', $userIds);
        $this->assertContains('charlie', $userIds);
    }

    public function testListMembersOfNonexistentGroupReturns404(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups/999/members')),
            404,
            'not_found',
        );
    }

    public function testListMembersIsPublic(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/members", null, 'stranger')
            ),
        );

        $this->assertCount(1, $body['data']);
    }

    public function testListMembersAfterOneLeaves(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$this->groupId}/members", null, 'bob')
        );

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );

        $this->assertCount(1, $body['data']);
        $this->assertSame('alice', $body['data'][0]['user_id']);
    }

    public function testListMembersResponseHasDataAndMetaKeys(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('count', $body['meta']);
        $this->assertArrayHasKey('has_more', $body['meta']);
    }

    public function testListMembersWithCursorPagination(): void
    {
        foreach (['bob', 'charlie', 'dave', 'eve'] as $user) {
            $this->joinGroup($this->groupId, $user);
        }

        // First page (newest first by id DESC: eve, dave, charlie)
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/members?limit=3")
            ),
        );

        $this->assertCount(3, $body['data']);
        $this->assertTrue($body['meta']['has_more']);

        // Second page
        $lastId = $body['data'][2]['id'];
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/members?limit=3&before_id=$lastId")
            ),
        );

        $this->assertCount(2, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testListMembersInvalidLimitReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/members?limit=abc")
            ),
            422,
            'validation_error',
        );
    }

    public function testListMembersLimitExceedsMax(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/members?limit=101")
            ),
            422,
            'validation_error',
        );
    }

    public function testListMembersInvalidBeforeIdReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/members?before_id=abc")
            ),
            422,
            'validation_error',
        );
    }
}
