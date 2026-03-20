<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Proxy;

use Laminas\Service_Manager\Exception;
use Laminas\Service_Manager\Factory\Delegator_Factory_Interface;
use Proxy_Manager\Factory\Lazy_Loading_Value_Holder_Factory;
use Proxy_Manager\Proxy\Lazy_Loading_Interface;
use Proxy_Manager\Proxy\Virtual_Proxy_Interface;
use Psr\Container\Container_Interface;
use function sprintf;
/**
 * Delegator factory responsible for instantiating lazy loading value holder proxies of
 * given services at runtime
 *
 * @link https://github.com/Ocramius/ProxyManager/blob/master/docs/lazy-loading-value-holder.md
 */
final readonly class Lazy_Service_Factory implements Delegator_Factory_Interface
{
    /**
     * @param array<string, class-string> $servicesMap A map of service names to
     *     class names of their respective classes
     */
    public function __construct(private Lazy_Loading_Value_Holder_Factory $proxy_factory, private array $services_map)
    {
    }
    /**
     * {@inheritDoc}
     */
    public function __invoke(Container_Interface $container, string $name, callable $callback, ?array $options = null): Virtual_Proxy_Interface
    {
        if (isset($this->services_map[$name])) {
            $initializer = static function (&$wrapped_instance, Lazy_Loading_Interface $proxy) use ($callback): bool {
                $proxy->set_proxy_initializer(null);
                $wrapped_instance = $callback();
                return true;
            };
            return $this->proxy_factory->create_proxy($this->services_map[$name], $initializer);
        }
        throw new Exception\Service_Not_Found_Exception(sprintf('The requested service "%s" was not found in the provided services map', $name));
    }
}