<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Psr\Container\Container_Exception_Interface;
use Psr\Container\Container_Interface;
use Psr\Container\Not_Found_Exception_Interface;
/**
 * Extended PSR-11 container interface with support for uncached service construction.
 *
 * `get()` returns a shared (cached) instance; `build()` always creates a fresh
 * instance, optionally with per-call configuration options.
 *
 * @since 3.0.0
 */
interface Service_Locator_Interface extends Container_Interface
{
    /**
     * Create a fresh service instance, bypassing the shared instance cache.
     *
     * Unlike `get()`, the constructed service is NEVER stored in the container's
     * service cache, even if `shared_by_default` is true. Use `build()` when you
     * need a new instance each call, or when you need to supply per-call options.
     *
     * @template T of object
     * @param string|class-string<T> $name    The service name or fully-qualified class name
     * @param array<mixed>|null      $options Optional constructor/configuration options passed
     *                                        to the factory; must be null for shared services
     * @psalm-return ($name is class-string<T> ? T : mixed)
     * @throws Service_Not_Found_Exception      If no factory or abstract factory can create $name.
     * @throws Service_Not_Created_Exception    If the factory throws during construction.
     * @throws Container_Exception_Interface    If any other container error occurs.
     * @since 3.0.0
     */
    public function build(string $name, ?array $options = null): mixed;
    /**
     * Retrieve a (potentially shared) service by name.
     *
     * The first call constructs the service; subsequent calls return the cached
     * instance unless `shared` is set to false for the service name.
     * Alias names are resolved transparently before construction.
     *
     * @template T of object
     * @param string|class-string<T> $id The service name or fully-qualified class name
     * @psalm-return ($id is class-string<T> ? T : mixed)
     * @throws Container_Exception_Interface         If an error occurs while retrieving the entry.
     * @throws Not_Found_Exception_Interface         If no entry exists for $id.
     * @since 3.0.0
     */
    public function get(string $id): mixed;
}