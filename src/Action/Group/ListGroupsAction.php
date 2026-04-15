<?php

declare(strict_types=1);

namespace App\Action\Group;

use App\Domain\Repository\GroupRepository;
use App\Infrastructure\Http\RequestValidator;
use App\Responder\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListGroupsAction
{
    private const int DEFAULT_LIMIT = 50;
    private const int MAX_LIMIT = 100;

    public function __construct(
        private GroupRepository $groupRepository,
        private RequestValidator $validator,
        private JsonResponder $responder,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $params = $request->getQueryParams();

        $limit = $this->validator->queryInt($params, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $beforeId = isset($params['before_id'])
            ? $this->validator->queryInt($params, 'before_id', 0, 1, PHP_INT_MAX)
            : null;

        $groups = $this->groupRepository->findAll($limit + 1, $beforeId);

        $hasMore = count($groups) > $limit;
        if ($hasMore) {
            array_pop($groups);
        }

        return $this->responder->json($response, [
            'data' => array_map(fn ($g) => $g->toArray(), $groups),
            'meta' => [
                'count' => count($groups),
                'has_more' => $hasMore,
            ],
        ]);
    }
}
