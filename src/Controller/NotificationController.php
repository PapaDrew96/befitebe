<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\NotificationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class NotificationController extends BaseController
{
    public function __construct(private readonly NotificationService $notifications) {}
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response, $this->notifications->mine($this->userId($request))); }
    public function markRead(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { $this->notifications->markRead((int)$args['id'], $this->userId($request)); return ApiResponse::success($response, null, 'Notification marked as read.'); }
    public function markAllRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { $this->notifications->markAllRead($this->userId($request)); return ApiResponse::success($response, null, 'Notifications marked as read.'); }
}
