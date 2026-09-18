<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Exception\ApiException;
use Befit\Http\ApiResponse;
use Befit\Service\ScheduleService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminScheduleController extends BaseController
{
    public function __construct(private readonly ScheduleService $schedule)
    {
    }

    public function templates(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success($response, $this->schedule->templates());
    }

    public function createTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->schedule->createTemplate($this->body($request), $this->userId($request)),
            'Schedule template created.',
            201
        );
    }

    public function updateTemplate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->schedule->updateTemplate((int) $args['id'], $this->body($request), $this->userId($request)),
            'Schedule template updated.'
        );
    }

    public function deleteTemplate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->schedule->deleteTemplate((int) $args['id'], $this->userId($request));
        return ApiResponse::success($response, null, 'Schedule template deactivated.');
    }

    public function sessions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $from = (string) ($query['from'] ?? date('Y-m-d'));
        $to = (string) ($query['to'] ?? (new DateTimeImmutable('+30 days'))->format('Y-m-d'));
        return ApiResponse::success($response, $this->schedule->adminSessions($from, $to));
    }

    public function createSession(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->schedule->createSession($this->body($request), $this->userId($request)),
            'Session created.',
            201
        );
    }

    public function updateSession(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->schedule->updateSession((int) $args['id'], $this->body($request), $this->userId($request)),
            'Session updated.'
        );
    }

    public function closures(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        if (empty($query['from']) || empty($query['to'])) {
            throw ApiException::validation([
                'range' => ['Both from and to query parameters are required.']
            ]);
        }
        return ApiResponse::success(
            $response,
            $this->schedule->closures((string) $query['from'], (string) $query['to'])
        );
    }

    public function createClosure(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return ApiResponse::success(
            $response,
            $this->schedule->createClosure($this->body($request), $this->userId($request)),
            'Closure created.',
            201
        );
    }

    public function deleteClosure(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->schedule->deleteClosure((int) $args['id'], $this->userId($request));
        return ApiResponse::success($response, null, 'Closure deleted.');
    }
}
