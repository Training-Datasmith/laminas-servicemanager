<?php

declare(strict_types=1);

/**
 * Example: Basic service container usage with laminas-servicemanager.
 *
 * Demonstrates registering services via factories, invokables, aliases,
 * and instances, then retrieving them from the container.
 *
 * Run standalone:
 *   php examples/basic_container.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Service_Manager\Service_Manager;
use Laminas\Service_Manager\Factory\Invokable_Factory;

// --- Example domain classes ---

class Logger
{
    public function log(string $message): void
    {
        echo "[LOG] $message" . PHP_EOL;
    }
}

class UserRepository
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function find(int $id): array
    {
        $this->logger->log("Finding user $id");
        return ['id' => $id, 'name' => 'Alice'];
    }
}

// --- Build the container ---

$container = new Service_Manager([
    // 'invokables' maps a name to a class that needs no constructor arguments
    'invokables' => [
        Logger::class => Logger::class,
    ],

    // 'factories' maps a name to a callable or factory class
    'factories' => [
        UserRepository::class => static function ($container) {
            return new UserRepository($container->get(Logger::class));
        },
    ],

    // 'aliases' allow multiple names for the same service
    'aliases' => [
        'logger' => Logger::class,
    ],

    // 'services' injects pre-built instances directly
    'services' => [
        'app.version' => '1.0.0',
    ],
]);

// --- Use the container ---

/** @var UserRepository $repo */
$repo = $container->get(UserRepository::class);
$user = $repo->find(42);
echo "Found: " . $user['name'] . PHP_EOL;

// Retrieve via alias
/** @var Logger $log */
$log = $container->get('logger');
$log->log("Retrieved via alias");

// Services are shared by default — same instance
$log1 = $container->get(Logger::class);
$log2 = $container->get(Logger::class);
echo "Shared: " . ($log1 === $log2 ? 'yes' : 'no') . PHP_EOL;

// build() always creates a fresh instance (never cached)
$log3 = $container->build(Logger::class);
echo "Built fresh: " . ($log1 === $log3 ? 'same' : 'new instance') . PHP_EOL;
