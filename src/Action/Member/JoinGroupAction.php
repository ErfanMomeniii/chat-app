<?php

declare(strict_types=1);

namespace App\Action\Member;

use App\Domain\Repository\MemberRepository;
use App\Domain\Service\GroupService;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class JoinGroupAction
{
    public function __construct(
        private GroupService $groupService,
        private MemberRepository $memberRepository,
        private JsonResponder $responder,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $userId = $request->getAttribute('userId');
        $groupId = (int) $args['id'];

        $this->groupService->getOrFail($groupId);

        $member = $this->memberRepository->add($groupId, $userId);

        $this->logger->info('User joined group', [
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        return $this->responder->json($response, $member->toArray(), 201);
    }
}
