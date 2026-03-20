<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Psr\Container\Container_Exception_Interface;
use Psr\Container\Container_Interface;
/**
 * Interface for a factory
 *
 * A factory is an callable object that is able to create a service. It is
 * given the instance of the service locator, the requested name of the service
 * you want to create, and any additional options that could be used to
 * configure the service state.
 */
interface Factory_Interface
{
    /**
     * @throws ServiceNotFoundException If unable to resolve the service.
     * @throws ServiceNotCreatedException If an exception is raised when creating a service.
     * @throws ContainerExceptionInterface If any other error occurs.
     */
    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): mixed;
}