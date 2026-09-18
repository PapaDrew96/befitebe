<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\ScheduleService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ScheduleController extends BaseController
{
    public function __construct(private readonly ScheduleService $schedule)
    {
    }

    public function week(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        return ApiResponse::success(
            $response,
            $this->schedule->week($query['date'] ?? null, $this->userId($request))
        );
    }
}
