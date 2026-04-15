<?php

declare(strict_types=1);

namespace Tests\Action\Group;

use Tests\TestCase;

final class UpdateGroupActionTest extends TestCase
{
    public function testUpdateGroup(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $response = $this->app->handle(
            $this->createRequest('PATCH', "/groups/{$groupId}", [
                'name' => 'updated',
                'description' => 'New description',
            ], 'alice')
        );
        $body = $this->assertJsonResponse($response);

        $this->assertSame($groupId, $body['id']);
        $this->assertSame('updated', $body['name']);
        $this->assertSame('New description', $body['description']);
        $this->assertSame('alice', $body['created_by']);
    }

    public function testUpdateGroupWithoutDescription(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'updated',
                ], 'alice')
            ),
        );

        $this->assertSame('updated', $body['name']);
        $this->assertSame('', $body['description']);
    }

    public function testUpdateGroupForbiddenForNonCreator(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'hacked',
                ], 'bob')
            ),
            403,
            'forbidden',
        );
    }

    public function testUpdateGroupNotFound(): void
    {
        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', '/groups/999', [
                    'name' => 'nope',
                ])
            ),
            404,
            'not_found',
        );
    }

    public function testUpdateGroupDuplicateName(): void
    {
        $this->createGroup('existing', 'alice');
        $groupId = $this->createGroup('original', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'existing',
                ], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testUpdateGroupMissingName(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'description' => 'no name',
                ], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testUpdateGroupEmptyName(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => '',
                ], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testUpdateGroupNameTooLong(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => str_repeat('a', 101),
                ], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testUpdateGroupDescriptionTooLong(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $this->assertJsonError(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'valid',
                    'description' => str_repeat('x', 501),
                ], 'alice')
            ),
            422,
            'validation_error',
        );
    }

    public function testUpdateGroupPreservesCreatedAt(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $original = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('GET', "/groups/{$groupId}", null, 'alice')
            ),
        );

        $updated = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'updated',
                ], 'alice')
            ),
        );

        $this->assertSame($original['created_at'], $updated['created_at']);
    }

    public function testUpdateGroupResponseHasExactKeys(): void
    {
        $groupId = $this->createGroup('original', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'updated',
                ], 'alice')
            ),
        );

        $this->assertSame(
            ['id', 'name', 'description', 'created_by', 'created_at'],
            array_keys($body)
        );
    }

    public function testUpdateGroupSameNameIsAllowed(): void
    {
        $groupId = $this->createGroup('same-name', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle(
                $this->createRequest('PATCH', "/groups/{$groupId}", [
                    'name' => 'same-name',
                    'description' => 'updated desc',
                ], 'alice')
            ),
        );

        $this->assertSame('same-name', $body['name']);
        $this->assertSame('updated desc', $body['description']);
    }
}
