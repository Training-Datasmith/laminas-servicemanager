<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver;

use function array_map;
use ArrayAccess;
use function assert;
use function class_exists;
use function in_array;
use function interface_exists;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Psr\Container\Container_Interface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use function sprintf;
/**
 * @internal
 */
final class Constructor_Parameter_Resolver implements Constructor_Parameter_Resolver_Interface
{
    /** {@inheritDoc} */
    public function resolve_constructor_parameters(string $class_name, Container_Interface $container, array $aliases = []): array
    {
        $parameters = $this->resolve_constructor_parameter_service_names_or_fallback_types($class_name, $container, $aliases);
        return array_map(static function (Fallback_Constructor_Parameter|Service_From_Container_Constructor_Parameter $parameter) use ($container): mixed {
            if ($parameter instanceof Fallback_Constructor_Parameter) {
                return $parameter->argument_value;
            }
            return $container->get($parameter->service_name);
        }, $parameters);
    }
    /**
     * Resolve a parameter to a value.
     *
     * Returns a callback for resolving a parameter to a value, but without
     * allowing mapping array `$config` arguments to the `config` service.
     *
     * @param class-string $className
     * @param array<string,string> $aliases
     * @return callable(ReflectionParameter):(FallbackConstructorParameter|ServiceFromContainerConstructorParameter)
     */
    private function resolve_parameter_without_config_service(Container_Interface $container, string $class_name, array $aliases): callable
    {
        return fn(ReflectionParameter $parameter): Fallback_Constructor_Parameter|Service_From_Container_Constructor_Parameter => $this->resolve_parameter($parameter, $container, $class_name, $aliases);
    }
    /**
     * Returns a callback for resolving a parameter to a value, including mapping 'config' arguments.
     *
     * Unlike resolveParameter(), this version will detect `$config` array
     * arguments and have them return the 'config' service.
     *
     * @param class-string $className
     * @param array<string,string> $aliases
     * @return callable(ReflectionParameter):(FallbackConstructorParameter|ServiceFromContainerConstructorParameter)
     */
    private function resolve_parameter_with_config_service(Container_Interface $container, string $class_name, array $aliases): callable
    {
        return function (ReflectionParameter $parameter) use ($container, $class_name, $aliases): Fallback_Constructor_Parameter|Service_From_Container_Constructor_Parameter {
            if ($parameter->get_name() === 'config') {
                $type = $parameter->get_type();
                if ($type instanceof ReflectionNamedType && in_array($type->get_name(), ['array', ArrayAccess::class], true)) {
                    return new Service_From_Container_Constructor_Parameter('config');
                }
            }
            return $this->resolve_parameter($parameter, $container, $class_name, $aliases);
        };
    }
    /**
     * Logic common to all parameter resolution.
     *
     * @param class-string $className
     * @param array<string,string> $aliases
     * @throws ServiceNotFoundException If type-hinted parameter cannot be
     *   resolved to a service in the container.
     */
    private function resolve_parameter(ReflectionParameter $parameter, Container_Interface $container, string $class_name, array $aliases): Fallback_Constructor_Parameter|Service_From_Container_Constructor_Parameter
    {
        $type = $parameter->get_type();
        $type = $type instanceof ReflectionNamedType ? $type->get_name() : null;
        if ($type === null || !class_exists($type) && !interface_exists($type)) {
            if (!$parameter->is_default_value_available()) {
                throw new Service_Not_Found_Exception(sprintf('Unable to create service "%s"; unable to resolve parameter "%s" ' . 'to a class, interface, or array type', $class_name, $parameter->get_name()));
            }
            return new Fallback_Constructor_Parameter($parameter->get_default_value());
        }
        $type = $aliases[$type] ?? $type;
        if ($container->has($type)) {
            assert($type !== '');
            return new Service_From_Container_Constructor_Parameter($type);
        }
        if (!$parameter->is_optional()) {
            throw new Service_Not_Found_Exception(sprintf('Unable to create service "%s"; unable to resolve parameter "%s" using type hint "%s"', $class_name, $parameter->get_name(), $type));
        }
        // Type not available in container, but the value is optional and has a
        // default defined.
        return new Fallback_Constructor_Parameter($parameter->get_default_value());
    }
    /** {@inheritDoc} */
    public function resolve_constructor_parameter_service_names_or_fallback_types(string $class_name, Container_Interface $container, array $aliases = []): array
    {
        $reflection_class = new ReflectionClass($class_name);
        $constructor = $reflection_class->get_constructor();
        if (null === $constructor) {
            return [];
        }
        $reflection_parameters = $constructor->get_parameters();
        if ($reflection_parameters === []) {
            return [];
        }
        $resolver = $container->has('config') ? $this->resolve_parameter_with_config_service($container, $class_name, $aliases) : $this->resolve_parameter_without_config_service($container, $class_name, $aliases);
        return array_map($resolver, $reflection_parameters);
    }
}