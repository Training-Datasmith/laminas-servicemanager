<?php

declare(strict_types=1);

/**
 * Example: Using an abstract factory for convention-based service creation.
 *
 * Abstract factories act as a catch-all when no named factory is registered.
 * They inspect the requested service name and decide whether they can handle it.
 *
 * Run standalone:
 *   php examples/abstract_factory.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Service_Manager\Service_Manager;
use Laminas\Service_Manager\Factory\Abstract_Factory_Interface;
use Psr\Container\Container_Interface;

/**
 * A simple abstract factory that creates any class whose name ends in 'Service'.
 */
class Service_Suffix_Abstract_Factory implements Abstract_Factory_Interface
{
    public function can_create(Container_Interface $container, string $requested_name): bool
    {
        return str_ends_with($requested_name, 'Service') && class_exists($requested_name);
    }

    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): mixed
    {
        return new $requested_name();
    }
}

// --- Example service classes ---

class EmailService
{
    public function send(string $to, string $body): void
    {
        echo "Sending email to $to: $body" . PHP_EOL;
    }
}

class SmsService
{
    public function send(string $to, string $body): void
    {
        echo "Sending SMS to $to: $body" . PHP_EOL;
    }
}

// --- Configure the container with the abstract factory ---

$container = new Service_Manager([
    'abstract_factories' => [
        Service_Suffix_Abstract_Factory::class,
    ],
]);

// Both classes are created via the abstract factory — no named registration needed
/** @var EmailService $email */
$email = $container->get(EmailService::class);
$email->send('alice@example.com', 'Hello!');

/** @var SmsService $sms */
$sms = $container->get(SmsService::class);
$sms->send('+441234567890', 'Hello via SMS!');

echo "has(EmailService): " . ($container->has(EmailService::class) ? 'yes' : 'no') . PHP_EOL;
