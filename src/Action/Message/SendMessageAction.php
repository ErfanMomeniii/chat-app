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
use Psr\Log\LoggerInterface;

final readonly class SendMessageAction
{
    private const int MAX_CONTENT_LENGTH = 5000;

    public function __construct(
        private GroupService $groupService,
        private MemberRepository $memberRepository,
        private MessageRepository $messageRepository,
        private RequestValidator $validator,
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
        $body = $this->validator->parseBody($request);

        $this->groupService->getOrFail($groupId);

        if (!$this->memberRepository->isMember($groupId, $userId)) {
            throw new ForbiddenException(
                'Only group members can send messages'
            );
        }

        $content = $this->validator->requireString(
            $body,
            'content',
            1,
            self::MAX_CONTENT_LENGTH
        );

        $message = $this->messageRepository->create(
            $groupId,
            $userId,
            $content
        );

        $this->logger->info('Message sent', [
            'message_id' => $message->id,
            'group_id' => $groupId,
            'user_id' => $userId,
            'content_length' => mb_strlen($content),
        ]);

        return $this->responder->json($response, $message->toArray(), 201);
    }
}
