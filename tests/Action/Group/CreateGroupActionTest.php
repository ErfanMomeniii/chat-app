<?php

declare(strict_types=1);

namespace Tests\Action\Group;

use Tests\TestCase;

final class CreateGroupActionTest extends TestCase
{
    public function testCreateGroup(): void
    {
        $response = $this->app->handle(
            $this->createRequest('POST', '/groups', [
                'name' => 'general',
                'description' => 'General discussion',
            ])
        );
        $body = $this->assertJsonResponse($response, 201);

        $this->assertIsInt($body['id']);
        $this->assertGreaterThan(0, $body['id']);
        $this->assertSame('general', $body['name']);
        $this->assertSame('General discussion', $body['description']);
        $this->assertSame('test-user', $body['created_by']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $body['created_at']);
    }

    public function testCreateGroupWithoutDescription(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'no-desc'])),
            201,
        );

        $this->assertSame('', $body['description']);
    }

    public function testCreateGroupMinimumNameLength(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'a'])),
            201,
        );

        $this->assertSame('a', $body['name']);
    }

    public function testCreateGroupNameAtMaxLength(): void
    {
        $name = str_repeat('a', 100);
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => $name])),
            201,
        );

        $this->assertSame($name, $body['name']);
    }

    public function testCreateGroupDescriptionAtMaxLength(): void
    {
        $desc = str_repeat('x', 500);
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => 'max-desc',
                'description' => $desc,
            ])),
            201,
        );

        $this->assertSame($desc, $body['description']);
    }

    public function testCreateGroupUnicodeNameAndDescription(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => 'チャット-グループ',
                'description' => 'Описание группы 🚀',
            ])),
            201,
        );

        $this->assertSame('チャット-グループ', $body['name']);
        $this->assertSame('Описание группы 🚀', $body['description']);
    }

    public function testCreateGroupMissingName(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['description' => 'no name'])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupEmptyName(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => ''])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupWhitespaceOnlyName(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => '   '])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupNameTooLong(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => str_repeat('a', 101)])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupDescriptionTooLong(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => 'valid',
                'description' => str_repeat('x', 501),
            ])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupNonStringName(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 123])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupNullName(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => null])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupMalformedJson(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createJsonRequest('POST', '/groups', 'not valid json')),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupJsonArrayBody(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createJsonRequest('POST', '/groups', '["not","an","object"]')),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupEmptyBody(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createJsonRequest('POST', '/groups', '')),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupDuplicateName(): void
    {
        $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'general']));

        $response = $this->app->handle(
            $this->createRequest('POST', '/groups', ['name' => 'general'])
        );

        $this->assertJsonError($response, 422, 'validation_error');
        $body = $this->getResponseBody($response);
        $this->assertStringContainsString('already exists', $body['error']['message']);
    }

    public function testDuplicateGroupDoesNotCorruptState(): void
    {
        $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'original']));
        $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'original']));

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('original', $body['data'][0]['name']);
    }

    public function testCreatorAutoJoinsGroup(): void
    {
        $groupId = $this->createGroup('test-group');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}/members")),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('test-user', $body['data'][0]['user_id']);

        // Creator can immediately send messages without explicit join
        $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', "/groups/{$groupId}/messages", ['content' => 'hello'])),
            201,
        );
    }

    public function testCreateGroupIgnoresUnknownFields(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => 'valid-group',
                'unknown_field' => 'ignored',
            ])),
            201,
        );

        $this->assertArrayNotHasKey('unknown_field', $body);
    }

    public function testSqlInjectionInGroupName(): void
    {
        $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => "'; DROP TABLE groups; --",
            ])),
            201,
        );

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );
        $this->assertCount(1, $body['data']);
    }

    public function testValidRequestAfterValidationError(): void
    {
        // First request fails
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => ''])),
            422,
            'validation_error',
        );

        // Next valid request succeeds
        $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'valid'])),
            201,
        );
    }

    public function testCreateGroupWithDifferentCreator(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'alice-group'], 'alice')),
            201,
        );
        $this->assertSame('alice', $body['created_by']);

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'bob-group'], 'bob')),
            201,
        );
        $this->assertSame('bob', $body['created_by']);
    }

    public function testCreateGroupNameTrimsWhitespace(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => '  trimmed  '])),
            201,
        );

        $this->assertSame('trimmed', $body['name']);
    }

    public function testCreateGroupDescriptionTrimsWhitespace(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => 'test',
                'description' => '  trimmed desc  ',
            ])),
            201,
        );

        $this->assertSame('trimmed desc', $body['description']);
    }

    public function testCreateGroupNonStringDescription(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', [
                'name' => 'valid',
                'description' => 123,
            ])),
            422,
            'validation_error',
        );
    }

    public function testCreateGroupAutoJoinIsAtomic(): void
    {
        // After failed duplicate, original group still has its creator as member
        $groupId = $this->createGroup('atomic-test', 'alice');

        // Duplicate attempt fails
        $this->assertJsonError(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'atomic-test'], 'bob')),
            422,
            'validation_error',
        );

        // Original group still has alice as member, no corruption
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups/{$groupId}/members")),
        );
        $this->assertCount(1, $body['data']);
        $this->assertSame('alice', $body['data'][0]['user_id']);
    }

    public function testCreateGroupResponseHasExactKeys(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'key-test'])),
            201,
        );

        $this->assertSame(['id', 'name', 'description', 'created_by', 'created_at'], array_keys($body));
    }

    public function testCreateGroupAutoIncrementIds(): void
    {
        $body1 = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'first'])),
            201,
        );
        $body2 = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('POST', '/groups', ['name' => 'second'])),
            201,
        );

        $this->assertGreaterThan($body1['id'], $body2['id']);
    }
}
