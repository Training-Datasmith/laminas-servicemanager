<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Psr\Container\Container_Interface;
/**
 * Interface for an abstract (catch-all) factory.
 *
 * An abstract factory is polled only after no concrete factory is found for a
 * requested service name. `can_create()` is called first; only if it returns
 * true will `__invoke()` be called to construct the service.
 *
 * Performance note: every registered abstract factory is checked on every
 * cache-miss lookup. Limit abstract factories to avoid O(n) overhead on
 * service resolution.
 *
 * @since 3.0.0
 */
interface Abstract_Factory_Interface extends Factory_Interface
{
    /**
     * Determine whether this factory can create a service for the given name.
     *
     * Implementations should be fast (no side effects) as this method is called
     * on every cache-miss before any service is constructed.
     *
     * @param Container_Interface $container      The service container
     * @param string              $requested_name The service name to test
     * @return bool True if this factory can create a service for $requested_name
     * @complexity O(1) — implementations must avoid expensive operations
     * @since 3.0.0
     */
    public function can_create(Container_Interface $container, string $requested_name): bool;
}