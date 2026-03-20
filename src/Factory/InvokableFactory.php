<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Psr\Container\Container_Interface;
/**
 * Factory for instantiating classes with no dependencies or which accept a single array.
 *
 * The InvokableFactory can be used for any class that:
 *
 * - has no constructor arguments;
 * - accepts a single array of arguments via the constructor.
 *
 * It replaces the "invokables" and "invokable class" functionality of the v2
 * service manager.
 */
final class Invokable_Factory implements Factory_Interface
{
    /** {@inheritDoc} */
    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): mixed
    {
        return null === $options ? new $requested_name() : new $requested_name($options);
    }
}