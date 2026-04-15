<?php

declare(strict_types=1);

namespace Tests\Action;

use Tests\TestCase;

/**
 * End-to-end test walking through the complete user journey:
 * create group -> join -> send messages -> list messages -> leave -> verify access revoked.
 */
final class UserJourneyTest extends TestCase
{
    public function testCompleteUserJourney(): void
    {
        // 1. Alice creates a group
        $group = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', '/groups', [
                    'name' => 'engineering',
                    'description' => 'Engineering team chat',
                ], 'alice')
            ),
            201,
        );
        $groupId = $group['id'];
        $this->assertSame('engineering', $group['name']);
        $this->assertSame('Engineering team chat', $group['description']);

        // 2. Group appears in the list
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups', null, 'alice')),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('engineering', $body['data'][0]['name']);
        $this->assertSame(1, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);

        // 3. Group details are correct
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}", null, 'alice')),
        );
        $this->assertSame($groupId, $body['id']);
        $this->assertSame('alice', $body['created_by']);

        // 4. Bob joins the group
        $member = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$groupId}/members", null, 'bob')
            ),
            201,
        );
        $this->assertSame('bob', $member['user_id']);
        $this->assertIsInt($member['id']);

        // 5. Both are visible in the member list
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}/members", null, 'alice')),
        );
        $this->assertCount(2, $body['data']);
        $this->assertSame(2, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);
        $userIds = array_column($body['data'], 'user_id');
        $this->assertContains('alice', $userIds);
        $this->assertContains('bob', $userIds);

        // 6. Alice sends a message
        $msg1 = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$groupId}/messages", [
                    'content' => 'Welcome to the team, Bob!',
                ], 'alice')
            ),
            201,
        );
        $this->assertSame('alice', $msg1['user_id']);

        // 7. Bob sends a reply
        $msg2 = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$groupId}/messages", [
                    'content' => 'Thanks Alice!',
                ], 'bob')
            ),
            201,
        );
        $this->assertSame('bob', $msg2['user_id']);

        // 8. Bob reads all messages (newest first)
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}/messages", null, 'bob')
            ),
        );
        $this->assertCount(2, $body['data']);
        $this->assertSame('Thanks Alice!', $body['data'][0]['content']);
        $this->assertSame('Welcome to the team, Bob!', $body['data'][1]['content']);
        $this->assertSame(2, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);

        // 9. Bob leaves the group
        $response = $this->app->handle(
            $this->createRequest('DELETE', "/groups/{$groupId}/members", null, 'bob')
        );
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());

        // 10. Bob can no longer read messages
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}/messages", null, 'bob')
            ),
            403,
            'forbidden',
        );

        // 11. Bob can no longer send messages
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$groupId}/messages", [
                    'content' => 'Should not work',
                ], 'bob')
            ),
            403,
            'forbidden',
        );

        // 12. Alice still sees 2 messages (Bob's message persists after leaving)
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}/messages", null, 'alice')
            ),
        );
        $this->assertCount(2, $body['data']);

        // 13. Member list now only shows Alice
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}/members", null, 'alice')),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('alice', $body['data'][0]['user_id']);
        $this->assertSame(1, $body['meta']['count']);
    }
}
