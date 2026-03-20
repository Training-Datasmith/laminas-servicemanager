<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Abstract_Factory;

use function class_exists;
use Laminas\Service_Manager\Exception\InvalidArgumentException;
use Laminas\Service_Manager\Factory\Abstract_Factory_Interface;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Constructor_Parameter_Resolver;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Constructor_Parameter_Resolver_Interface;
use Psr\Container\Container_Interface;
use ReflectionClass;
use function sprintf;
/**
 * Reflection-based factory.
 *
 * To ease development, this factory may be used for classes with
 * type-hinted arguments that resolve to services in the application
 * container; this allows omitting the step of writing a factory for
 * each controller.
 *
 * You may use it as either an abstract factory:
 *
 * <code>
 * 'service_manager' => [
 *     'abstract_factories' => [
 *         ReflectionBasedAbstractFactory::class,
 *     ],
 * ],
 * </code>
 *
 * Or as a factory, mapping a class name to it:
 *
 * <code>
 * 'service_manager' => [
 *     'factories' => [
 *         MyClassWithDependencies::class => ReflectionBasedAbstractFactory::class,
 *     ],
 * ],
 * </code>
 *
 * The latter approach is more explicit, and also more performant.
 *
 * The factory has the following constraints/features:
 *
 * - A parameter named `$config` typehinted as an array will receive the
 *   application "config" service (i.e., the merged configuration).
 * - Parameters type-hinted against array, but not named `$config` will
 *   be injected with an empty array.
 * - Scalar parameters will result in an exception being thrown, unless
 *   a default value is present; if the default is present, that will be used.
 * - If a service cannot be found for a given typehint, the factory will
 *   raise an exception detailing this.
 * - Some services provided by Laminas components do not have
 *   entries based on their class name (for historical reasons); the
 *   factory allows defining a map of these class/interface names to the
 *   corresponding service name to allow them to resolve.
 *
 * `$options` passed to the factory are ignored in all cases, as we cannot
 * make assumptions about which argument(s) they might replace.
 *
 * Based on the LazyControllerAbstractFactory from laminas-mvc.
 */
final readonly class Reflection_Based_Abstract_Factory implements Abstract_Factory_Interface
{
    /**
     * Allows overriding the internal list of aliases. These should be of the
     * form `class name => well-known service name`; see the documentation for
     * the `$aliases` property for details on what is accepted.
     *
     * @param array<string,string> $aliases
     */
    public function __construct(public array $aliases = [], private ?Constructor_Parameter_Resolver_Interface $constructor_parameter_resolver = new Constructor_Parameter_Resolver())
    {
    }
    /**
     * {@inheritDoc}
     */
    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): object
    {
        if (!class_exists($requested_name)) {
            throw new InvalidArgumentException(sprintf('%s can only be used with class names.', self::class));
        }
        $parameters = $this->constructor_parameter_resolver->resolve_constructor_parameters($requested_name, $container, $this->aliases);
        return new $requested_name(...$parameters);
    }
    /** {@inheritDoc} */
    public function can_create(Container_Interface $container, string $requested_name): bool
    {
        return class_exists($requested_name) && $this->can_call_constructor($requested_name);
    }
    private function can_call_constructor(string $requested_name): bool
    {
        $constructor = (new ReflectionClass($requested_name))->get_constructor();
        return $constructor === null || $constructor->is_public();
    }
}