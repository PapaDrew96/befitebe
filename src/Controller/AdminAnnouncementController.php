<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\AnnouncementService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminAnnouncementController extends BaseController
{
    public function __construct(private readonly AnnouncementService $announcements)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, $this->announcements->all());
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success($response, $this->announcements->get((int) $args['id']));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->announcements->create($this->body($request), $this->userId($request)),
            'Announcement created.',
            201
        );
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->announcements->update((int) $args['id'], $this->body($request), $this->userId($request)),
            'Announcement updated.'
        );
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->announcements->delete((int) $args['id'], $this->userId($request));
        return ApiResponse::success($response, null, 'Announcement deleted.');
    }
}
