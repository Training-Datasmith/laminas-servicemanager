<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Abstract_Factory;

use function array_key_exists;
use function array_map;
use function array_values;
use ArrayObject;
use function is_array;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Factory\Abstract_Factory_Interface;
use Psr\Container\Container_Interface;
final class Config_Abstract_Factory implements Abstract_Factory_Interface
{
    /**
     * Factory can create the service if there is a key for it in the config
     *
     * {@inheritdoc}
     */
    public function can_create(Container_Interface $container, string $requested_name): bool
    {
        if (!$container->has('config')) {
            return false;
        }
        $config = $container->get('config');
        if (!isset($config[self::class])) {
            return false;
        }
        $dependencies = $config[self::class];
        return is_array($dependencies) && array_key_exists($requested_name, $dependencies);
    }
    /** {@inheritDoc} */
    public function __invoke(Container_Interface $container, string $requested_name, ?array $options = null): mixed
    {
        if (!$container->has('config')) {
            throw new Service_Not_Created_Exception('Cannot find a config array in the container');
        }
        $config = $container->get('config');
        if (!(is_array($config) || $config instanceof ArrayObject)) {
            throw new Service_Not_Created_Exception('Config must be an array or an instance of ArrayObject');
        }
        if (!isset($config[self::class])) {
            throw new Service_Not_Created_Exception('Cannot find a `' . self::class . '` key in the config array');
        }
        $dependencies = $config[self::class];
        if (!is_array($dependencies) || !array_key_exists($requested_name, $dependencies) || !is_array($dependencies[$requested_name])) {
            throw new Service_Not_Created_Exception('Service dependencies config must exist and be an array');
        }
        $service_dependencies = $dependencies[$requested_name];
        if ($service_dependencies !== array_values(array_map(strval(...), $service_dependencies))) {
            $problem = json_encode(array_map(gettype(...), $service_dependencies), JSON_THROW_ON_ERROR);
            throw new Service_Not_Created_Exception('Service dependencies config must be an array of strings, ' . $problem . ' given');
        }
        $arguments = array_map($container->get(...), $service_dependencies);
        return new $requested_name(...$arguments);
    }
}