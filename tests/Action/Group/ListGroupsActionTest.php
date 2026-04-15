<?php

declare(strict_types=1);

namespace Tests\Action\Group;

use Tests\TestCase;

final class ListGroupsActionTest extends TestCase
{
    public function testListGroupsEmpty(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testListGroupsReturnsAll(): void
    {
        $this->createGroup('group-a');
        $this->createGroup('group-b');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertCount(2, $body['data']);
        $this->assertSame(2, $body['meta']['count']);
        $this->assertFalse($body['meta']['has_more']);
    }

    public function testListGroupsReturnsCorrectFieldTypes(): void
    {
        $this->createGroup('my-group');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $group = $body['data'][0];
        $this->assertIsInt($group['id']);
        $this->assertIsString($group['name']);
        $this->assertIsString($group['description']);
        $this->assertIsString($group['created_by']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $group['created_at']);
    }

    public function testListGroupsEachGroupHasExactKeys(): void
    {
        $this->createGroup('key-test');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertSame(['id', 'name', 'description', 'created_by', 'created_at'], array_keys($body['data'][0]));
    }

    public function testListGroupsReturnsNewestFirst(): void
    {
        $this->pdo->exec(
            "INSERT INTO groups (name, description, created_by, created_at) "
            . "VALUES ('old', '', 'alice', '2024-01-01T00:00:00Z')"
        );
        $this->pdo->exec(
            "INSERT INTO groups (name, description, created_by, created_at) "
            . "VALUES ('new', '', 'bob', '2024-06-01T00:00:00Z')"
        );

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertCount(2, $body['data']);
        $this->assertSame('new', $body['data'][0]['name']);
        $this->assertSame('old', $body['data'][1]['name']);
    }

    public function testListGroupsAccessibleByAnyUser(): void
    {
        $this->createGroup('public-group', 'alice');

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups', null, 'stranger')),
        );

        $this->assertCount(1, $body['data']);
    }

    public function testListGroupsResponseHasDataAndMetaKeys(): void
    {
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('count', $body['meta']);
        $this->assertArrayHasKey('has_more', $body['meta']);
    }

    public function testListGroupsWithCursorPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createGroup("group-$i");
        }

        // First page
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups?limit=2')),
        );

        $this->assertCount(2, $body['data']);
        $this->assertTrue($body['meta']['has_more']);
        $this->assertSame('group-5', $body['data'][0]['name']);
        $this->assertSame('group-4', $body['data'][1]['name']);

        // Second page
        $lastId = $body['data'][1]['id'];
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups?limit=2&before_id=$lastId")),
        );

        $this->assertCount(2, $body['data']);
        $this->assertTrue($body['meta']['has_more']);
        $this->assertSame('group-3', $body['data'][0]['name']);
        $this->assertSame('group-2', $body['data'][1]['name']);

        // Third page - last item
        $lastId = $body['data'][1]['id'];
        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', "/groups?limit=2&before_id=$lastId")),
        );

        $this->assertCount(1, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
        $this->assertSame('group-1', $body['data'][0]['name']);
    }

    public function testListGroupsInvalidLimitReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups?limit=abc')),
            422,
            'validation_error',
        );
    }

    public function testListGroupsLimitExceedsMax(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups?limit=101')),
            422,
            'validation_error',
        );
    }

    public function testListGroupsLimitZeroReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups?limit=0')),
            422,
            'validation_error',
        );
    }

    public function testListGroupsInvalidBeforeIdReturns422(): void
    {
        $this->assertJsonError(
            $this->app->handle($this->createRequest('GET', '/groups?before_id=abc')),
            422,
            'validation_error',
        );
    }

    public function testListGroupsDefaultLimit(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->createGroup("group-$i");
        }

        $body = $this->assertJsonResponse(
            $this->app->handle($this->createRequest('GET', '/groups')),
        );

        $this->assertCount(3, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
    }
}
