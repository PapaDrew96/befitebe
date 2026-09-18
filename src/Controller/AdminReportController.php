<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\ReportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminReportController extends BaseController
{
    public function __construct(private readonly ReportService $reports) {}
    public function attendance(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response, $this->reports->attendance($request->getQueryParams())); }
}
