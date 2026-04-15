<?php

declare(strict_types=1);

namespace App\Action\Member;

use App\Domain\Repository\MemberRepository;
use App\Domain\Service\GroupService;
use App\Infrastructure\Http\RequestValidator;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListMembersAction
{
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private GroupService $groupService,
        private MemberRepository $memberRepository,
        private RequestValidator $validator,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $groupId = (int) $args['id'];
        $params = $request->getQueryParams();

        $this->groupService->getOrFail($groupId);

        $limit = $this->validator->queryInt($params, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $beforeId = isset($params['before_id'])
            ? $this->validator->queryInt($params, 'before_id', 0, 1, PHP_INT_MAX)
            : null;

        $members = $this->memberRepository->findByGroup($groupId, $limit + 1, $beforeId);

        $hasMore = count($members) > $limit;
        if ($hasMore) {
            array_pop($members);
        }

        return $this->responder->json($response, [
            'data' => array_map(fn ($m) => $m->toArray(), $members),
            'meta' => [
                'count' => count($members),
                'has_more' => $hasMore,
            ],
        ]);
    }
}
