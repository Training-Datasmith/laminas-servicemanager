<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Factory;

use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Psr\Container\Container_Exception_Interface;
use Psr\Container\Container_Interface;
/**
 * Interface for a service factory.
 *
 * A factory is a callable object responsible for constructing and returning a
 * fully-configured service instance. Factories receive the IoC container so they
 * can pull collaborators and configuration from it.
 *
 * Factories registered as class names are lazily instantiated on first use and
 * then cached to avoid re-instantiation overhead on subsequent requests.
 *
 * @since 3.0.0
 */
interface Factory_Interface
{
    /**
     * Create and return a service instance.
     *
     * @param Container_Interface $container     The service container used to resolve dependencies
     * @param string              $requested_name The canonical (resolved) service name being created
     * @param array<mixed>|null   $options        Optional construction-time configuration array;
     *                                             services built with options are NEVER cached
     * @return mixed The constructed service instance
     * @throws Service_Not_Found_Exception      If unable to resolve a required dependency.
     * @throws Service_Not_Created_Exception    If an exception is raised during service construction.
     * @throws Container_Exception_Interface    If any other container-level error occurs.
     * @since 3.0.0
     */
    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): mixed;
}