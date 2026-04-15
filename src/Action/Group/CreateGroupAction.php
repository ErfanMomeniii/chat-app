<?php

declare(strict_types=1);

namespace App\Action\Group;

use App\Domain\Service\GroupService;
use App\Infrastructure\Http\RequestValidator;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateGroupAction
{
    public function __construct(
        private GroupService $groupService,
        private RequestValidator $validator,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $userId = $request->getAttribute('userId');
        $body = $this->validator->parseBody($request);

        $name = $this->validator->requireString($body, 'name', 1, 100);
        $description = $this->validator->optionalString(
            $body,
            'description',
            500
        );

        $group = $this->groupService->createWithMember(
            $name,
            $description,
            $userId
        );

        return $this->responder->json($response, $group->toArray(), 201);
    }
}
