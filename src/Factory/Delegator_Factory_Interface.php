<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Psr\Container\Container_Exception_Interface;
use Psr\Container\Container_Interface;
/**
 * Delegator factory interface.
 *
 * Defines the capabilities required by a delegator factory. Delegator
 * factories are used to either decorate a service instance, or to allow
 * decorating the instantiation of a service instance (for instance, to
 * provide optional dependencies via setters, etc.).
 */
interface Delegator_Factory_Interface
{
    /**
     * A factory that creates delegates of a given service
     *
     * @param callable():mixed $callback
     * @throws ServiceNotFoundException If unable to resolve the service.
     * @throws ServiceNotCreatedException If an exception is raised when creating a service.
     * @throws ContainerExceptionInterface If any other error occurs.
     */
    public function __invoke(Container_Interface $container, string $name, callable $callback, ?array $options = null): mixed;
}