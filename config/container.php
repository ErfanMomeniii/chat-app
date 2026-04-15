<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\DatabaseFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    'settings' => fn () => require __DIR__ . '/settings.php',

    PDO::class => function (ContainerInterface $c): PDO {
        $settings = $c->get('settings');

        return DatabaseFactory::createAndMigrate($settings['db']['path']);
    },

    LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
        $settings = $c->get('settings');
        $logger = new Logger('chat-app');
        $level = ($settings['debug'] ?? false) ? Level::Debug : Level::Info;
        $logger->pushHandler(new StreamHandler('php://stderr', $level));

        return $logger;
    },
];
