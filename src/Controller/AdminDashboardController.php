<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\DashboardService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminDashboardController extends BaseController
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        return ApiResponse::success(
            $response,
            $this->dashboard->summary($query['date'] ?? null, $this->userId($request))
        );
    }
}
