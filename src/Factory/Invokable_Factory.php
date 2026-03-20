<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Psr\Container\Container_Interface;
/**
 * Generic factory for classes that need no injected dependencies.
 *
 * Use this factory for any class that:
 * - has a no-argument constructor; OR
 * - accepts a single array of configuration options as its only constructor parameter.
 *
 * Replaces the legacy v2 "invokables" / "invokable class" registration style.
 *
 * @since 3.0.0
 */
final class Invokable_Factory implements Factory_Interface
{
    /**
     * Instantiate the requested class, forwarding $options to the constructor if provided.
     *
     * @param Container_Interface $container      Unused — invokables have no injected deps
     * @param string              $requested_name Fully-qualified class name to instantiate
     * @param array<mixed>|null   $options        Optional constructor arguments array; if null,
     *                                             the class is constructed with no arguments
     * @return mixed The new instance of $requested_name
     * @since 3.0.0
     */
    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): mixed
    {
        return null === $options ? new $requested_name() : new $requested_name($options);
    }
}