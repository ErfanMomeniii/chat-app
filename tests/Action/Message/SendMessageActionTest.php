<?php

declare(strict_types=1);

namespace Tests\Action\Message;

use Tests\TestCase;

final class SendMessageActionTest extends TestCase
{
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groupId = $this->createGroup('test-group', 'alice');
    }

    public function testSendMessageReturnsCorrectFieldTypes(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", [
                    'content' => 'Hello everyone',
                ], 'alice')
            ),
            201,
        );

        $this->assertIsInt($body['id']);
        $this->assertGreaterThan(0, $body['id']);
        $this->assertSame('Hello everyone', $body['content']);
        $this->assertIsString($body['content']);
        $this->assertSame('alice', $body['user_id']);
        $this->assertIsString($body['user_id']);
        $this->assertSame($this->groupId, $body['group_id']);
        $this->assertIsInt($body['group_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['created_at']);
    }

    public function testCannotSendMessageWithoutJoining(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", [
                    'content' => 'I am not a member',
                ], 'stranger')
            ),
            403,
            'forbidden',
        );
    }

    public function testCannotSendEmptyMessage(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['content' => ''], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testCannotSendWhitespaceOnlyMessage(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['content' => '   '], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testCannotSendMessageExceedingMaxLength(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", [
                    'content' => str_repeat('a', 5001),
                ], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testCanSendMessageAtMaxLength(): void
    {
        $content = str_repeat('a', 5000);
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", [
                    'content' => $content,
                ], 'alice')
            ),
            201,
        );

        $this->assertSame($content, $body['content']);
    }

    public function testCannotSendMessageToNonexistentGroup(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', '/groups/999/messages', ['content' => 'Hello'])
            ),
            404,
            'not_found',
        );
    }

    public function testCannotSendNonStringContent(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['content' => 12345], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testCannotSendMissingContent(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['text' => 'wrong field'], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testCannotSendWithMalformedJson(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createJsonRequest('POST', "/groups/{$this->groupId}/messages", '{invalid', 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testSendUnicodeMessage(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", [
                    'content' => 'Привет мир 🚀 こんにちは',
                ], 'alice')
            ),
            201,
        );

        $this->assertSame('Привет мир 🚀 こんにちは', $body['content']);
    }

    public function testCannotSendNullContent(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", ['content' => null], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testCannotSendEmptyBody(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createJsonRequest('POST', "/groups/{$this->groupId}/messages", '', 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testMessageIgnoresUnknownFields(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('POST', "/groups/{$this->groupId}/messages", [
                    'content' => 'valid',
                    'unknown' => 'ignored',
                ], 'alice')
            ),
            201,
        );

        $this->assertArrayNotHasKey('unknown', $body);
    }
}
