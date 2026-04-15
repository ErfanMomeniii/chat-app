<?php

declare(strict_types=1);

use App\Infrastructure\Http\CorrelationIdMiddleware;
use App\Infrastructure\Http\JsonErrorHandler;
use App\Infrastructure\Http\SecurityHeadersMiddleware;
use Psr\Log\LoggerInterface;
use Slim\App;

return function (App $app): void {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(new SecurityHeadersMiddleware());
    $app->add(new CorrelationIdMiddleware());

    $settings = $app->getContainer()->get('settings');
    $logger = $app->getContainer()->get(LoggerInterface::class);
    $displayErrorDetails = $settings['debug'] ?? false;
    $logErrors = $settings['log_errors'] ?? true;
    $errorMiddleware = $app->addErrorMiddleware(
        $displayErrorDetails,
        $logErrors,
        $logErrors
    );
    $errorMiddleware->setDefaultErrorHandler(
        new JsonErrorHandler($logger)
    );
};
