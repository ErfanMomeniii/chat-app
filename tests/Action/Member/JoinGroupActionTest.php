<?php

declare(strict_types=1);

namespace Tests\Action\Member;

use Tests\TestCase;

final class JoinGroupActionTest extends TestCase
{
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupId = $this->createGroup('test-group', 'alice');
    }

    public function testJoinGroupReturnsCorrectFieldTypes(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/members", null, 'bob')
            ),
            201,
        );

        $this->assertIsInt($body['id']);
        $this->assertIsInt($body['group_id']);
        $this->assertSame($this->groupId, $body['group_id']);
        $this->assertIsString($body['user_id']);
        $this->assertSame('bob', $body['user_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['joined_at']);
    }

    public function testJoinNonexistentGroupReturns404(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups/999/members')),
            404,
            'not_found',
        );
    }

    public function testJoinGroupIsIdempotent(): void
    {
        $this->joinGroup($this->groupId, 'bob');

        $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/members", null, 'bob')
            ),
            201,
        );

        // Should still be only 2 members (alice + bob)
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );
        $this->assertCount(2, $body['data']);
    }

    public function testJoinGroupAllowsPublicAccess(): void
    {
        foreach (['bob', 'charlie', 'dave'] as $user) {
            $this->assertJsonResponse(
                $this->app->handle(
                    $this->createRequest('POST', "/groups/{$this->groupId}/members", null, $user)
                ),
                201,
            );
        }

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );
        $this->assertCount(4, $body['data']); // alice + 3
    }

    public function testCreatorIsAlreadyMember(): void
    {
        // Creator (alice) joining again should be idempotent
        $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/members", null, 'alice')
            ),
            201,
        );

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$this->groupId}/members")),
        );
        $this->assertCount(1, $body['data']);
    }
}
