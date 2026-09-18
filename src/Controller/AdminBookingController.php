<?php

declare(strict_types=1);

namespace Befit\Controller;

use Befit\Http\ApiResponse;
use Befit\Service\BookingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminBookingController extends BaseController
{
    public function __construct(private readonly BookingService $bookings) {}
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response,$this->bookings->adminList($request->getQueryParams())); }
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response,$this->bookings->createAdmin($this->body($request),$this->userId($request)),'Booking created successfully.',201); }
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { $this->bookings->cancelAdmin((int)$args['id'],$this->userId($request)); return ApiResponse::success($response,null,'Booking cancelled successfully.'); }
    public function attendance(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface { return ApiResponse::success($response,$this->bookings->markAttendance((int)$args['id'],$this->body($request),$this->userId($request)),'Attendance updated.'); }
    public function waitlist(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { return ApiResponse::success($response,$this->bookings->adminWaitlist($request->getQueryParams())); }
}
