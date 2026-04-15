<?php

declare(strict_types=1);

namespace App\Action\Member;

use App\Domain\Exception\ForbiddenException;
use App\Domain\Repository\MemberRepository;
use App\Domain\Service\GroupService;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

final readonly class LeaveGroupAction
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

        if (!$this->memberRepository->isMember($groupId, $userId)) {
            throw new ForbiddenException(
                'You are not a member of this group'
            );
        }

        $this->memberRepository->remove($groupId, $userId);

        $this->logger->info('User left group', [
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        return $this->responder->empty($response);
    }
}
