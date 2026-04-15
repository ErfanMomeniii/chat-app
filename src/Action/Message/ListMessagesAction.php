<?php

declare(strict_types=1);

namespace App\Action\Message;

use App\Domain\Exception\ForbiddenException;
use App\Domain\Repository\MemberRepository;
use App\Domain\Repository\MessageRepository;
use App\Domain\Service\GroupService;
use App\Infrastructure\Http\RequestValidator;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListMessagesAction
{
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private GroupService $groupService,
        private MemberRepository $memberRepository,
        private MessageRepository $messageRepository,
        private RequestValidator $validator,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $userId = $request->getAttribute('userId');
        $groupId = (int) $args['id'];
        $params = $request->getQueryParams();

        $this->groupService->getOrFail($groupId);

        if (!$this->memberRepository->isMember($groupId, $userId)) {
            throw new ForbiddenException('Only group members can view messages');
        }

        $limit = $this->validator->queryInt($params, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $beforeId = isset($params['before_id'])
            ? $this->validator->queryInt($params, 'before_id', 0, 1, PHP_INT_MAX)
            : null;

        // Fetch one extra to detect if more pages exist
        $messages = $this->messageRepository->findByGroup($groupId, $limit + 1, $beforeId);

        $hasMore = count($messages) > $limit;
        if ($hasMore) {
            array_pop($messages);
        }

        return $this->responder->json($response, [
            'data' => array_map(fn ($m) => $m->toArray(), $messages),
            'meta' => [
                'count' => count($messages),
                'has_more' => $hasMore,
            ],
        ]);
    }
}
