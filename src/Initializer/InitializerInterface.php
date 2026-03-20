<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Initializer;

use Psr\Container\Container_Interface;
/**
 * Interface for an initializer
 *
 * An initializer can be registered to a service locator, and are run after an instance is created
 * to inject additional dependencies through setters
 */
interface Initializer_Interface
{
    /**
     * Initialize the given service
     *
     * @param  mixed $instance
     * @return void
     */
    public function __invoke(Container_Interface $container, $instance);
}