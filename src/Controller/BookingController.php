<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\BookingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class BookingController extends BaseController
{
    public function __construct(private readonly BookingService $bookings) {}
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response,$this->bookings->mine($this->userId($request),$request->getQueryParams())); }
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response,$this->bookings->createMember($this->body($request),$this->userId($request)),'Booking created successfully.',201); }
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { $this->bookings->cancelMember((int)$args['id'],$this->userId($request)); return ApiResponse::success($response,null,'Booking cancelled successfully.'); }
    public function joinWaitlist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response,$this->bookings->joinWaitlist($this->body($request),$this->userId($request)),'Added to waiting list.',201); }
    public function leaveWaitlist(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { $this->bookings->leaveWaitlist((int)$args['id'],$this->userId($request)); return ApiResponse::success($response,null,'Removed from waiting list.'); }
}
