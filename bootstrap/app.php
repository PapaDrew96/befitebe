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
use Befit\Database\Database;
use Befit\Http\ApiErrorHandler;
use Befit\Middleware\CorsMiddleware;
use Befit\Middleware\RequestIdMiddleware;
use Befit\Middleware\RequestSecurityMiddleware;
use Befit\Middleware\SecurityHeadersMiddleware;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\AnnouncementRepository;
use Befit\Repository\BookingRepository;
use Befit\Repository\DashboardRepository;
use Befit\Repository\NotificationRepository;
use Befit\Repository\PasswordResetRepository;
use Befit\Repository\PaymentRepository;
use Befit\Repository\RateLimitRepository;
use Befit\Repository\ReportRepository;
use Befit\Repository\ScheduleRepository;
use Befit\Repository\SettingRepository;
use Befit\Repository\TokenRepository;
use Befit\Repository\UserRepository;
use Befit\Repository\WaitlistRepository;
use Befit\Security\PasswordHasher;
use Befit\Security\PasswordPolicy;
use Befit\Security\ProductionSecurity;
use Befit\Service\AnnouncementService;
use Befit\Service\AuthService;
use Befit\Service\BookingService;
use Befit\Service\DashboardService;
use Befit\Service\MailService;
use Befit\Service\NotificationService;
use Befit\Service\PasswordResetService;
use Befit\Service\PaymentService;
use Befit\Service\ReportService;
use Befit\Service\ScheduleService;
use Befit\Service\SettingsService;
use Befit\Service\UserService;
use Befit\Support\ClientIpResolver;
use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Slim\Factory\AppFactory;

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$config = require $root . '/config/app.php';
ProductionSecurity::assertSafe($config);
date_default_timezone_set($config['app']['timezone']);

$logDirectory = dirname($config['logging']['file']);
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0775, true);
}

$levels = [
    'debug' => Level::Debug,
    'info' => Level::Info,
    'notice' => Level::Notice,
    'warning' => Level::Warning,
    'error' => Level::Error,
    'critical' => Level::Critical,
    'alert' => Level::Alert,
    'emergency' => Level::Emergency,
];
$logLevel = $levels[strtolower($config['logging']['level'])] ?? Level::Info;

$logger = new Logger('befit-api');
$logger->pushHandler(new StreamHandler($config['logging']['file'], $logLevel));

$database = new Database($config['database']);
$pdo = $database->pdo();

$userRepository = new UserRepository($pdo);
$tokenRepository = new TokenRepository($pdo);
$scheduleRepository = new ScheduleRepository($pdo);
$bookingRepository = new BookingRepository($pdo);
$dashboardRepository = new DashboardRepository($pdo);
$announcementRepository = new AnnouncementRepository($pdo);
$settingRepository = new SettingRepository($pdo);
$rateLimitRepository = new RateLimitRepository($pdo);
$activityRepository = new ActivityLogRepository($pdo);
$waitlistRepository = new WaitlistRepository($pdo);
$notificationRepository = new NotificationRepository($pdo);
$passwordResetRepository = new PasswordResetRepository($pdo);
$reportRepository = new ReportRepository($pdo);
$paymentRepository = new PaymentRepository($pdo);

$passwordHasher = new PasswordHasher(
    $config['auth']['argon_memory_kb'],
    $config['auth']['argon_time_cost'],
    $config['auth']['argon_threads'],
    $config['auth']['bcrypt_cost']
);
$passwordPolicy = new PasswordPolicy($config['auth']['password_min_length']);
$clientIpResolver = new ClientIpResolver($config['security']['trusted_proxies']);

$authService = new AuthService(
    $database,
    $userRepository,
    $tokenRepository,
    $activityRepository,
    $passwordHasher,
    $config['auth']['token_ttl_days'],
    $config['auth']['token_max_active_per_user']
);
$userService = new UserService(
    $database,
    $userRepository,
    $tokenRepository,
    $activityRepository,
    $passwordHasher,
    $passwordPolicy
);
$scheduleService = new ScheduleService(
    $database,
    $scheduleRepository,
    $bookingRepository,
    $settingRepository,
    $waitlistRepository,
    $notificationRepository,
    $activityRepository,
    $config['schedule']['max_days_ahead'],
    $config['app']['timezone']
);
$bookingService = new BookingService(
    $database,
    $bookingRepository,
    $scheduleRepository,
    $userRepository,
    $settingRepository,
    $waitlistRepository,
    $notificationRepository,
    $activityRepository,
    $config['app']['timezone']
);
$dashboardService = new DashboardService(
    $dashboardRepository,
    $scheduleService,
    $config['app']['timezone']
);
$announcementService = new AnnouncementService(
    $database,
    $announcementRepository,
    $activityRepository,
    $config['app']['timezone']
);
$settingsService = new SettingsService(
    $database,
    $settingRepository,
    $activityRepository
);
$notificationService = new NotificationService($notificationRepository);
$mailService = new MailService(
    $config['mail']['enabled'],
    $config['mail']['host'],
    $config['mail']['port'],
    $config['mail']['encryption'],
    $config['mail']['username'],
    $config['mail']['password'],
    $config['mail']['from'],
    $config['mail']['from_name']
);
$passwordResetService = new PasswordResetService(
    $database,
    $userRepository,
    $passwordResetRepository,
    $tokenRepository,
    $activityRepository,
    $config['app']['debug'],
    $config['auth']['password_reset_base_url'],
    $config['auth']['password_reset_ttl_minutes'],
    $mailService,
    $passwordHasher,
    $passwordPolicy,
    $config['app']['timezone']
);
$reportService = new ReportService($reportRepository, $config['app']['timezone']);
$paymentService = new PaymentService(
    $database,
    $paymentRepository,
    $userRepository,
    $notificationRepository,
    $activityRepository,
    $config['app']['timezone']
);

$c = [
    'config' => $config,
    'rate_limit_repository' => $rateLimitRepository,
    'token_repository' => $tokenRepository,
    'client_ip_resolver' => $clientIpResolver,
    HealthController::class => new HealthController($database),
    AuthController::class => new AuthController($authService, $userService),
    PasswordResetController::class => new PasswordResetController($passwordResetService),
    PaymentController::class => new PaymentController($paymentService),
    ProfileController::class => new ProfileController($userService),
    ScheduleController::class => new ScheduleController($scheduleService),
    BookingController::class => new BookingController($bookingService),
    AnnouncementController::class => new AnnouncementController($announcementService),
    NotificationController::class => new NotificationController($notificationService),
    AdminDashboardController::class => new AdminDashboardController($dashboardService),
    AdminUserController::class => new AdminUserController($userService, $paymentService),
    AdminPaymentController::class => new AdminPaymentController($paymentService),
    AdminScheduleController::class => new AdminScheduleController($scheduleService),
    AdminBookingController::class => new AdminBookingController($bookingService),
    AdminAnnouncementController::class => new AdminAnnouncementController($announcementService),
    AdminSettingsController::class => new AdminSettingsController($settingsService),
    AdminReportController::class => new AdminReportController($reportService),
];

$app = AppFactory::create();
if ($config['app']['base_path'] !== '') {
    $app->setBasePath($config['app']['base_path']);
}

$routes = require $root . '/routes/api.php';
$routes($app, $c);

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new RequestSecurityMiddleware(
    $app->getResponseFactory(),
    $config['security']['allowed_hosts'],
    $config['security']['max_request_body_bytes'],
    $config['security']['require_json_writes']
));

$errorMiddleware = $app->addErrorMiddleware(
    $config['app']['debug'],
    true,
    true,
    $logger
);
$errorMiddleware->setDefaultErrorHandler(new ApiErrorHandler(
    $app->getResponseFactory(),
    $logger,
    $config['app']['debug']
));

// Last-added middleware executes first in Slim. This order guarantees that
// security headers and request IDs are also present on CORS/security failures.
$app->add(new CorsMiddleware($app->getResponseFactory(), $config['cors']['allowed_origins']));
$app->add(new RequestIdMiddleware());
$app->add(new SecurityHeadersMiddleware());

return $app;
