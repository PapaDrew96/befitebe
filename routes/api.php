<?php

declare(strict_types=1);

use Befit\Controller\AdminAnnouncementController;
use Befit\Controller\AdminBookingController;
use Befit\Controller\AdminDashboardController;
use Befit\Controller\AdminPaymentController;
use Befit\Controller\AdminReportController;
use Befit\Controller\AdminScheduleController;
use Befit\Controller\AdminSettingsController;
use Befit\Controller\AdminUserController;
use Befit\Controller\AnnouncementController;
use Befit\Controller\AuthController;
use Befit\Controller\BookingController;
use Befit\Controller\HealthController;
use Befit\Controller\NotificationController;
use Befit\Controller\PasswordResetController;
use Befit\Controller\PaymentController;
use Befit\Controller\ProfileController;
use Befit\Controller\ScheduleController;
use Befit\Middleware\AuthenticationMiddleware;
use Befit\Middleware\RateLimitMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app, array $c): void {
    $rateLimit = static function (
        string $bucket,
        int $limit,
        int $windowSeconds,
        string $strategy
    ) use ($c): RateLimitMiddleware {
        return new RateLimitMiddleware(
            $c['rate_limit_repository'],
            $c['client_ip_resolver'],
            $c['config']['security']['app_key'],
            $bucket,
            $limit,
            $windowSeconds,
            $strategy
        );
    };

    $app->group('/api/v1', function (RouteCollectorProxy $api) use ($c, $rateLimit): void {
        $api->get('/health', [$c[HealthController::class], 'show']);

        $api->post('/auth/forgot-password', [$c[PasswordResetController::class], 'forgot'])
            ->add($rateLimit(
                'auth.forgot_password.subject',
                $c['config']['auth']['password_reset_rate_limit_attempts'],
                $c['config']['auth']['password_reset_rate_limit_window_seconds'],
                RateLimitMiddleware::STRATEGY_SUBJECT
            ))
            ->add($rateLimit(
                'auth.forgot_password.ip',
                $c['config']['auth']['password_reset_ip_rate_limit_attempts'],
                $c['config']['auth']['password_reset_rate_limit_window_seconds'],
                RateLimitMiddleware::STRATEGY_IP
            ));

        $api->post('/auth/reset-password', [$c[PasswordResetController::class], 'reset'])
            ->add($rateLimit(
                'auth.reset_password.ip',
                $c['config']['auth']['password_reset_ip_rate_limit_attempts'],
                $c['config']['auth']['password_reset_rate_limit_window_seconds'],
                RateLimitMiddleware::STRATEGY_IP
            ));

        $api->post('/auth/login', [$c[AuthController::class], 'login'])
            ->add($rateLimit(
                'auth.login.subject',
                $c['config']['auth']['login_rate_limit_attempts'],
                $c['config']['auth']['login_rate_limit_window_seconds'],
                RateLimitMiddleware::STRATEGY_SUBJECT
            ))
            ->add($rateLimit(
                'auth.login.ip',
                $c['config']['auth']['login_ip_rate_limit_attempts'],
                $c['config']['auth']['login_rate_limit_window_seconds'],
                RateLimitMiddleware::STRATEGY_IP
            ));

        $api->group('', function (RouteCollectorProxy $member) use ($c): void {
            $member->post('/auth/logout', [$c[AuthController::class], 'logout']);
            $member->get('/auth/me', [$c[AuthController::class], 'me']);

            $member->get('/profile', [$c[ProfileController::class], 'show']);
            $member->put('/profile', [$c[ProfileController::class], 'update']);
            $member->put('/profile/password', [$c[ProfileController::class], 'changePassword']);

            $member->get('/schedule/week', [$c[ScheduleController::class], 'week']);

            $member->get('/bookings', [$c[BookingController::class], 'index']);
            $member->post('/bookings', [$c[BookingController::class], 'create']);
            $member->delete('/bookings/{id:[0-9]+}', [$c[BookingController::class], 'delete']);
            $member->post('/waitlist', [$c[BookingController::class], 'joinWaitlist']);
            $member->delete('/waitlist/{id:[0-9]+}', [$c[BookingController::class], 'leaveWaitlist']);

            $member->get('/notifications', [$c[NotificationController::class], 'index']);
            $member->patch('/notifications/read-all', [$c[NotificationController::class], 'markAllRead']);
            $member->patch('/notifications/{id:[0-9]+}/read', [$c[NotificationController::class], 'markRead']);

            $member->get('/announcements', [$c[AnnouncementController::class], 'index']);
            $member->get('/payments', [$c[PaymentController::class], 'index']);
        })->add(new AuthenticationMiddleware(
            $c['token_repository'],
            $c['config']['auth']['token_idle_ttl_minutes']
        ));

        $api->group('/admin', function (RouteCollectorProxy $admin) use ($c): void {
            $admin->get('/dashboard', [$c[AdminDashboardController::class], 'show']);

            $admin->get('/users', [$c[AdminUserController::class], 'index']);
            $admin->post('/users', [$c[AdminUserController::class], 'create']);
            $admin->get('/users/{id:[0-9]+}', [$c[AdminUserController::class], 'show']);
            $admin->put('/users/{id:[0-9]+}', [$c[AdminUserController::class], 'update']);
            $admin->get('/users/{id:[0-9]+}/payments', [$c[AdminPaymentController::class], 'index']);
            $admin->post('/users/{id:[0-9]+}/payments', [$c[AdminPaymentController::class], 'create']);

            $admin->get('/schedule/templates', [$c[AdminScheduleController::class], 'templates']);
            $admin->post('/schedule/templates', [$c[AdminScheduleController::class], 'createTemplate']);
            $admin->put('/schedule/templates/{id:[0-9]+}', [$c[AdminScheduleController::class], 'updateTemplate']);
            $admin->delete('/schedule/templates/{id:[0-9]+}', [$c[AdminScheduleController::class], 'deleteTemplate']);

            $admin->get('/schedule/sessions', [$c[AdminScheduleController::class], 'sessions']);
            $admin->post('/schedule/sessions', [$c[AdminScheduleController::class], 'createSession']);
            $admin->put('/schedule/sessions/{id:[0-9]+}', [$c[AdminScheduleController::class], 'updateSession']);

            $admin->get('/schedule/closures', [$c[AdminScheduleController::class], 'closures']);
            $admin->post('/schedule/closures', [$c[AdminScheduleController::class], 'createClosure']);
            $admin->delete('/schedule/closures/{id:[0-9]+}', [$c[AdminScheduleController::class], 'deleteClosure']);

            $admin->get('/bookings', [$c[AdminBookingController::class], 'index']);
            $admin->post('/bookings', [$c[AdminBookingController::class], 'create']);
            $admin->delete('/bookings/{id:[0-9]+}', [$c[AdminBookingController::class], 'delete']);
            $admin->patch('/bookings/{id:[0-9]+}/attendance', [$c[AdminBookingController::class], 'attendance']);
            $admin->get('/waitlist', [$c[AdminBookingController::class], 'waitlist']);
            $admin->get('/reports/attendance', [$c[AdminReportController::class], 'attendance']);

            $admin->get('/announcements', [$c[AdminAnnouncementController::class], 'index']);
            $admin->post('/announcements', [$c[AdminAnnouncementController::class], 'create']);
            $admin->get('/announcements/{id:[0-9]+}', [$c[AdminAnnouncementController::class], 'show']);
            $admin->put('/announcements/{id:[0-9]+}', [$c[AdminAnnouncementController::class], 'update']);
            $admin->delete('/announcements/{id:[0-9]+}', [$c[AdminAnnouncementController::class], 'delete']);

            $admin->get('/settings', [$c[AdminSettingsController::class], 'show']);
            $admin->put('/settings', [$c[AdminSettingsController::class], 'update']);
        })->add(new AuthenticationMiddleware(
            $c['token_repository'],
            $c['config']['auth']['token_idle_ttl_minutes'],
            'admin'
        ));
    });
};
