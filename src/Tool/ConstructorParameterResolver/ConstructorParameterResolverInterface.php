<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver;

use Psr\Container\Container_Interface;
interface Constructor_Parameter_Resolver_Interface
{
    /**
     * Returns already resolved values so that these can be directly passed into the constructor.
     *
     * @param class-string         $className
     * @param array<string,string> $aliases
     * @return list<mixed>
     */
    public function resolve_constructor_parameters(string $class_name, Container_Interface $container, array $aliases = []): array;
    /**
     * Returns service names and/or native fallback types which can be either used to retrieve services from container
     * or to be passed to the constructor directly.
     *
     * @param class-string         $className
     * @param array<string,string> $aliases
     * @return list<ServiceFromContainerConstructorParameter|FallbackConstructorParameter>
     */
    public function resolve_constructor_parameter_service_names_or_fallback_types(string $class_name, Container_Interface $container, array $aliases = []): array;
}