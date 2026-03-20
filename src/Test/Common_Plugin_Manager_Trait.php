<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Test;

use function assert;
use function is_string;
use Laminas\Service_Manager\Abstract_Plugin_Manager;
use Laminas\Service_Manager\Abstract_Single_Instance_Plugin_Manager;
use Laminas\Service_Manager\Exception\Invalid_Service_Exception;
use Laminas\Service_Manager\Service_Manager;
use Php_Unit\Framework\Attributes\Data_Provider;
use ReflectionProperty;
use stdClass;
/**
 * Trait for testing plugin managers for compatibility
 *
 * To use this trait:
 *   * implement the `getPluginManager()` method to return your plugin manager
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
trait Common_Plugin_Manager_Trait
{
    public function test_instance_of_matches(): void
    {
        $manager = self::get_plugin_manager();
        $reflection = new ReflectionProperty($manager, 'instanceOf');
        $this->assert_equals($this->get_instance_of(), $reflection->get_value($manager), 'instanceOf does not match');
    }
    public function test_registering_invalid_element_raises_exception(): void
    {
        $this->expect_exception($this->get_service_not_found_exception());
        self::get_plugin_manager()->set_service('test', $this);
    }
    public function test_loading_invalid_element_raises_exception(): void
    {
        $manager = self::get_plugin_manager();
        $manager->set_invokable_class('test', stdClass::class);
        $this->expect_exception($this->get_service_not_found_exception());
        $manager->get('test');
    }
    #[Data_Provider('aliasProvider')]
    public function test_plugin_aliases_resolve(string $alias, string $expected): void
    {
        $this->assert_instance_of($expected, self::get_plugin_manager()->get($alias), "Alias '{$alias}' does not resolve'");
    }
    /**
     * @return list<array{string,string}>
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function alias_provider(): array
    {
        $manager = self::get_plugin_manager();
        $plugin_container_property = new ReflectionProperty(Abstract_Plugin_Manager::class, 'plugins');
        $plugin_container = $plugin_container_property->get_value($manager);
        self::assert_instance_of(Service_Manager::class, $plugin_container);
        $reflection = new ReflectionProperty($plugin_container, 'aliases');
        $data = [];
        foreach ($reflection->get_value($plugin_container) as $alias => $expected) {
            assert(is_string($alias) && is_string($expected));
            $data[] = [$alias, $expected];
        }
        return $data;
    }
    protected function get_service_not_found_exception(): string
    {
        return Invalid_Service_Exception::class;
    }
    /**
     * Returns the plugin manager to test
     *
     * @param ServiceManagerConfiguration $config
     */
    abstract protected static function get_plugin_manager(array $config = []): Abstract_Single_Instance_Plugin_Manager;
    /**
     * Returns the value the instanceOf property has been set to
     *
     * @return class-string
     */
    abstract protected function get_instance_of(): string;
}