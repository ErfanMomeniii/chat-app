<?php

declare(strict_types=1);

use App\Action\Group\CreateGroupAction;
use App\Action\Group\DeleteGroupAction;
use App\Action\Group\GetGroupAction;
use App\Action\Group\ListGroupsAction;
use App\Action\Group\UpdateGroupAction;
use App\Action\HealthAction;
use App\Action\Member\JoinGroupAction;
use App\Action\Member\LeaveGroupAction;
use App\Action\Member\ListMembersAction;
use App\Action\Message\ListMessagesAction;
use App\Action\Message\SendMessageAction;
use App\Infrastructure\Http\UserIdentityMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->get('/health', HealthAction::class);

    $app->group('/groups', function (RouteCollectorProxy $group) {
        $group->post('', CreateGroupAction::class);
        $group->get('', ListGroupsAction::class);
        $group->get('/{id:[0-9]+}', GetGroupAction::class);
        $group->patch('/{id:[0-9]+}', UpdateGroupAction::class);
        $group->delete('/{id:[0-9]+}', DeleteGroupAction::class);

        $group->post('/{id:[0-9]+}/members', JoinGroupAction::class);
        $group->delete('/{id:[0-9]+}/members', LeaveGroupAction::class);
        $group->get('/{id:[0-9]+}/members', ListMembersAction::class);

        $group->post('/{id:[0-9]+}/messages', SendMessageAction::class);
        $group->get('/{id:[0-9]+}/messages', ListMessagesAction::class);
    })->add(new UserIdentityMiddleware());
};
