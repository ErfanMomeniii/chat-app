<?php

declare(strict_types=1);

namespace Tests\Action\Message;

use Tests\TestCase;

final class ListMessagesActionTest extends TestCase
{
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupId = $this->createGroup('test-group', 'alice');
    }

    public function testListMessagesReturnsCorrectFieldTypes(): void
    {
        $this->sendMessage($this->groupId, 'Hello', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertCount(1, $body['data']);
        $msg = $body['data'][0];
        $this->assertIsInt($msg['id']);
        $this->assertIsString($msg['content']);
        $this->assertSame('Hello', $msg['content']);
        $this->assertIsString($msg['user_id']);
        $this->assertSame('alice', $msg['user_id']);
        $this->assertIsInt($msg['group_id']);
        $this->assertSame($this->groupId, $msg['group_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $msg['created_at']);

        // Meta fields
        $this->assertIsBool($body['meta']['has_more']);
        $this->assertFalse($body['meta']['has_more']);
        $this->assertIsInt($body['meta']['count']);
        $this->assertSame(1, $body['meta']['count']);
    }

    public function testListMessagesNewestFirst(): void
    {
        $this->sendMessage($this->groupId, 'First message', 'alice');
        $this->sendMessage($this->groupId, 'Second message', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertCount(2, $body['data']);
        $this->assertSame('Second message', $body['data'][0]['content']);
        $this->assertSame('First message', $body['data'][1]['content']);
        $this->assertFalse($body['meta']['has_more']);
        $this->assertSame(2, $body['meta']['count']);
    }

    public function testListMessagesWithCursorPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->sendMessage($this->groupId, "Message $i", 'alice');
        }

        // First page
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=2", null, 'alice')
            ),
        );

        $this->assertCount(2, $body['data']);
        $this->assertTrue($body['meta']['has_more']);
        $this->assertSame('Message 5', $body['data'][0]['content']);
        $this->assertSame('Message 4', $body['data'][1]['content']);

        // Second page
        $lastId = $body['data'][1]['id'];
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest(
                    'GET',
                    "/groups/{$this->groupId}/messages?limit=2&before_id=$lastId",
                    null,
                    'alice'
                )
            ),
        );

        $this->assertCount(2, $body['data']);
        $this->assertTrue($body['meta']['has_more']);
        $this->assertSame('Message 3', $body['data'][0]['content']);
        $this->assertSame('Message 2', $body['data'][1]['content']);

        // Third page - last item
        $lastId = $body['data'][1]['id'];
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest(
                    'GET',
                    "/groups/{$this->groupId}/messages?limit=2&before_id=$lastId",
                    null,
                    'alice'
                )
            ),
        );

        $this->assertCount(1, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
        $this->assertSame('Message 1', $body['data'][0]['content']);
    }

    public function testCannotListMessagesWithoutJoining(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'stranger')
            ),
            403,
            'forbidden',
        );
    }

    public function testListMessagesEmptyGroup(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testListMessagesLimitOne(): void
    {
        $this->sendMessage($this->groupId, 'First', 'alice');
        $this->sendMessage($this->groupId, 'Second', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=1", null, 'alice')
            ),
        );

        $this->assertCount(1, $body['data']);
        $this->assertSame('Second', $body['data'][0]['content']);
        $this->assertTrue($body['meta']['has_more']);
    }

    public function testListMessagesLimitAtMax(): void
    {
        $this->sendMessage($this->groupId, 'Hello', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=100", null, 'alice')
            ),
        );

        $this->assertCount(1, $body['data']);
    }

    public function testListMessagesInvalidLimitReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=abc", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesLimitExceedsMax(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=999", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesInvalidBeforeId(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?before_id=abc", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesLimitZeroReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=0", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesNegativeLimitReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=-1", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesNonexistentGroup(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', '/groups/999/messages', null, 'alice')
            ),
            404,
            'not_found',
        );
    }

    public function testBeforeIdWithNonexistentIdReturnsEmpty(): void
    {
        $this->sendMessage($this->groupId, 'Hello', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?before_id=1", null, 'alice')
            ),
        );

        $this->assertSame([], $body['data']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testMessagesFromMultipleUsers(): void
    {
        $this->joinGroup($this->groupId, 'bob');
        $this->sendMessage($this->groupId, 'From Alice', 'alice');
        $this->sendMessage($this->groupId, 'From Bob', 'bob');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertCount(2, $body['data']);
        $this->assertSame('bob', $body['data'][0]['user_id']);
        $this->assertSame('alice', $body['data'][1]['user_id']);
    }

    public function testListMessagesDefaultLimit(): void
    {
        // Default limit is 50; sending 3 messages should return all 3
        $this->sendMessage($this->groupId, 'One', 'alice');
        $this->sendMessage($this->groupId, 'Two', 'alice');
        $this->sendMessage($this->groupId, 'Three', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertCount(3, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testListMessagesLimitAboveMaxReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?limit=101", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesResponseHasDataAndMetaKeys(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('count', $body['meta']);
        $this->assertArrayHasKey('has_more', $body['meta']);
    }

    public function testListMessagesEachMessageHasExactKeys(): void
    {
        $this->sendMessage($this->groupId, 'Hello', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertSame(['id', 'group_id', 'user_id', 'content', 'created_at'], array_keys($body['data'][0]));
    }

    public function testListMessagesIsolatedBetweenGroups(): void
    {
        $group2 = $this->createGroup('other-group', 'alice');

        $this->sendMessage($this->groupId, 'In group 1', 'alice');
        $this->sendMessage($group2, 'In group 2', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('In group 1', $body['data'][0]['content']);
    }

    public function testListMessagesBeforeIdZeroReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?before_id=0", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesNegativeBeforeIdReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages?before_id=-5", null, 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testListMessagesUnicodeContent(): void
    {
        $this->sendMessage($this->groupId, 'Привет 🌍 こんにちは', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$this->groupId}/messages", null, 'alice')
            ),
        );

        $this->assertSame('Привет 🌍 こんにちは', $body['data'][0]['content']);
    }
}
