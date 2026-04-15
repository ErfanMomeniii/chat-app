<?php

declare(strict_types=1);

namespace App\Action\Group;

use App\Domain\Service\GroupService;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DeleteGroupAction
{
    public function __construct(
        private GroupService $groupService,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $userId = $request->getAttribute('userId');

        $this->groupService->delete((int) $args['id'], $userId);

        return $this->responder->empty($response);
    }
}
