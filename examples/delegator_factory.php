<?php

declare(strict_types=1);

/**
 * Example: Using delegator factories to decorate services.
 *
 * A delegator factory wraps an existing service after it has been created,
 * without modifying the original factory. This implements the Decorator pattern
 * at the container level.
 *
 * Run standalone:
 *   php examples/delegator_factory.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laminas\Service_Manager\Factory\Delegator_Factory_Interface;
use Laminas\Service_Manager\Service_Manager;
use Psr\Container\Container_Interface;

// --- Domain classes ---

interface Cache_Interface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

class Array_Cache implements Cache_Interface
{
    /** @var array<string, mixed> */
    private array $store = [];

    public function get(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->store[$key] = $value;
    }
}

/**
 * Delegator that adds logging around every cache operation.
 */
class Logging_Cache_Delegator implements Delegator_Factory_Interface
{
    public function __invoke(
        Container_Interface $container,
        string $name,
        callable $callback,
        ?array $options = null
    ): Cache_Interface {
        /** @var Cache_Interface $cache */
        $cache = $callback();

        // Wrap with a simple anonymous proxy that logs operations
        return new class ($cache) implements Cache_Interface {
            public function __construct(private readonly Cache_Interface $inner)
            {
            }

            public function get(string $key): mixed
            {
                $value = $this->inner->get($key);
                echo "[CACHE HIT/MISS] get('$key') = " . ($value === null ? 'null' : 'hit') . PHP_EOL;
                return $value;
            }

            public function set(string $key, mixed $value): void
            {
                echo "[CACHE SET] set('$key')" . PHP_EOL;
                $this->inner->set($key, $value);
            }
        };
    }
}

// --- Configure container ---

$container = new Service_Manager([
    'factories' => [
        Cache_Interface::class => static fn() => new Array_Cache(),
    ],
    'delegators' => [
        Cache_Interface::class => [
            Logging_Cache_Delegator::class,
        ],
    ],
]);

/** @var Cache_Interface $cache */
$cache = $container->get(Cache_Interface::class);
$cache->set('user:1', ['name' => 'Alice']);
$result = $cache->get('user:1');
echo "Result: " . ($result['name'] ?? 'null') . PHP_EOL;
$cache->get('user:999'); // miss
