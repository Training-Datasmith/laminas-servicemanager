<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Psr\Container\Container_Interface;
/**
 * Interface for an abstract factory.
 *
 * An abstract factory extends the factory interface, but also has an
 * additional "canCreate" method, which is called to check if the abstract
 * factory has the ability to create an instance for the given service. You
 * should limit the number of abstract factories to ensure good performance.
 * Starting from ServiceManager v3, remember that you can also attach multiple
 * names to the same factory, which reduces the need for abstract factories.
 */
interface Abstract_Factory_Interface extends Factory_Interface
{
    /**
     * Can the factory create an instance for the service?
     */
    public function can_create(Container_Interface $container, string $requested_name): bool;
}