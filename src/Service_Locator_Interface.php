<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Psr\Container\Container_Exception_Interface;
use Psr\Container\Container_Interface;
use Psr\Container\Not_Found_Exception_Interface;
/**
 * Interface for service locator
 */
interface Service_Locator_Interface extends Container_Interface
{
    /**
     * Builds a service by its name, using optional options (such services are NEVER cached).
     *
     * @template T of object
     * @param  string|class-string<T> $name
     * @psalm-return ($name is class-string<T> ? T : mixed)
     * @throws ServiceNotFoundException If no factory/abstract
     *     factory could be found to create the instance.
     * @throws ServiceNotCreatedException If factory/delegator fails
     *     to create the instance.
     * @throws ContainerExceptionInterface If any other error occurs.
     */
    public function build(string $name, ?array $options = null): mixed;
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @template T of object
     * @param string|class-string<T> $id
     * @psalm-return ($id is class-string<T> ? T : mixed)
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     * @throws NotFoundExceptionInterface No entry was found for **this** identifier.
     */
    public function get(string $id);
}