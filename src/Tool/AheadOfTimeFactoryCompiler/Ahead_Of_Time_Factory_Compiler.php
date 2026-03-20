<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler;

use function array_filter;
use const ARRAY_FILTER_USE_BOTH;
use function array_key_exists;
use function class_exists;
use function enum_exists;
use function is_array;
use function is_string;
use Laminas\Service_Manager\Abstract_Factory\Reflection_Based_Abstract_Factory;
use Laminas\Service_Manager\Exception\InvalidArgumentException;
use Laminas\Service_Manager\Tool\Factory_Creator_Interface;
use function sprintf;
final readonly class Ahead_Of_Time_Factory_Compiler implements Ahead_Of_Time_Factory_Compiler_Interface
{
    public function __construct(private Factory_Creator_Interface $factory_creator)
    {
    }
    public function compile(array $config): array
    {
        $services_registered_by_reflection_based_factory = $this->extract_services_registered_by_reflection_based_factory($config);
        $compiled_factories = [];
        foreach ($services_registered_by_reflection_based_factory as $service => [$container_configuration_key, $aliases]) {
            $compiled_factories[] = new Ahead_Of_Time_Compiled_Factory($service, $container_configuration_key, $this->factory_creator->create_factory($service, $aliases));
        }
        return $compiled_factories;
    }
    /**
     * @return array<class-string,array{non-empty-string,array<string,string>}>
     */
    private function extract_services_registered_by_reflection_based_factory(array $config): array
    {
        $services = [];
        foreach ($config as $key => $entry) {
            if (!is_string($key)) {
                continue;
            }
            if ($key === '') {
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }
            if (!array_key_exists('factories', $entry)) {
                continue;
            }
            if (!is_array($entry['factories'])) {
                continue;
            }
            /** @var array<string,ReflectionBasedAbstractFactory|class-string<ReflectionBasedAbstractFactory>> $servicesUsingReflectionBasedFactory */
            $services_using_reflection_based_factory = array_filter($entry['factories'], static fn(mixed $value): bool => $value === Reflection_Based_Abstract_Factory::class || $value instanceof Reflection_Based_Abstract_Factory, ARRAY_FILTER_USE_BOTH);
            if ($services_using_reflection_based_factory === []) {
                continue;
            }
            foreach ($services_using_reflection_based_factory as $service => $factory) {
                if (!$this->can_service_be_used_with_reflection_based_factory($service)) {
                    throw new InvalidArgumentException(sprintf('Configured service "%s" using the `ReflectionBasedAbstractFactory` does not exist or does' . ' not refer to an actual class.', $service));
                }
                if (isset($services[$service])) {
                    throw new InvalidArgumentException(sprintf('The exact same service "%s" is registered in (at least) two service-/plugin-managers: %s, %s', $service, $services[$service][0], $key));
                }
                $aliases = [];
                if ($factory instanceof Reflection_Based_Abstract_Factory && $factory->aliases !== []) {
                    $aliases = $factory->aliases;
                }
                $services[$service] = [$key, $aliases];
            }
        }
        return $services;
    }
    /**
     * Starting with PHP 8.1, `class_exists` resolves to `true` for enums.
     *
     * @link https://3v4l.org/FY7eg
     *
     * @psalm-assert-if-true class-string $service
     */
    private function can_service_be_used_with_reflection_based_factory(string $service): bool
    {
        if (!class_exists($service)) {
            return false;
        }
        return !enum_exists($service);
    }
}