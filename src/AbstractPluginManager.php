<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

use function class_exists;
use Laminas\Service_Manager\Exception\Container_Modifications_Not_Allowed_Exception;
use Laminas\Service_Manager\Exception\Cyclic_Alias_Exception;
use Laminas\Service_Manager\Exception\Invalid_Service_Exception;
use Laminas\Service_Manager\Factory\Abstract_Factory_Interface;
use Laminas\Service_Manager\Factory\Delegator_Factory_Interface;
use Laminas\Service_Manager\Factory\Factory_Interface;
use Laminas\Service_Manager\Initializer\Initializer_Interface;
use Laminas\Stdlib\Array_Utils;
use Psr\Container\Container_Interface;
use function sprintf;
/**
 * Abstract plugin manager.
 *
 * Abstract PluginManagerInterface implementation providing creation context support.
 * The constructor accepts the parent container instance, which is then used when creating instances.
 *
 * @template InstanceType
 * @template-implements PluginManagerInterface<InstanceType>
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 * @psalm-import-type FactoryCallable from ServiceManager
 * @psalm-import-type DelegatorCallable from ServiceManager
 * @psalm-import-type InitializerCallable from ServiceManager
 * @psalm-import-type AbstractFactoriesConfiguration from ServiceManager
 * @psalm-import-type DelegatorsConfiguration from ServiceManager
 * @psalm-import-type FactoriesConfiguration from ServiceManager
 * @psalm-import-type InitializersConfiguration from ServiceManager
 * @psalm-import-type LazyServicesConfiguration from ServiceManager
 */
abstract class Abstract_Plugin_Manager implements Plugin_Manager_Interface
{
    /**
     * Whether or not to auto-add a FQCN as an invokable if it exists.
     */
    protected bool $auto_add_invokable_class = true;
    protected bool $shared_by_default = true;
    /**
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var AbstractFactoryInterface[]
     */
    protected array $abstract_factories = [];
    /**
     * A list of aliases
     *
     * Should map one alias to a service name, or another alias (aliases are recursively resolved)
     *
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var string[]
     */
    protected array $aliases = [];
    /**
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var DelegatorsConfiguration
     */
    protected array $delegators = [];
    /**
     * A list of factories (either as string name or callable)
     *
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var FactoriesConfiguration
     */
    protected array $factories = [];
    /**
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var InitializersConfiguration
     */
    protected array $initializers = [];
    /**
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var LazyServicesConfiguration
     */
    protected array $lazy_services = [];
    /**
     * A list of already loaded services (this act as a local cache)
     *
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var array<string,mixed>
     */
    protected array $services = [];
    /**
     * Enable/disable shared instances by service name.
     *
     * Example configuration:
     *
     * 'shared' => [
     *     MyService::class => true, // will be shared, even if "sharedByDefault" is false
     *     MyOtherService::class => false // won't be shared, even if "sharedByDefault" is true
     * ]
     *
     * @deprecated Please pass the plugin manager configuration via {@see AbstractPluginManager::__construct} instead.
     *
     * @var array<string,bool>
     */
    protected array $shared = [];
    private readonly Service_Manager $plugins;
    /**
     * @param ServiceManagerConfiguration $config
     */
    public function __construct(Container_Interface $creation_context, array $config = [])
    {
        $this->plugins = new Service_Manager(['shared_by_default' => $this->shared_by_default], $creation_context);
        /** @var ServiceManagerConfiguration $config */
        $config = Array_Utils::merge(['factories' => $this->factories, 'abstract_factories' => $this->abstract_factories, 'aliases' => $this->aliases, 'services' => $this->services, 'lazy_services' => $this->lazy_services, 'shared' => $this->shared, 'delegators' => $this->delegators, 'initializers' => $this->initializers], $config);
        $this->configure($config);
    }
    /**
     * @param ServiceManagerConfiguration $config
     * @throws ContainerModificationsNotAllowedException If the allow override flag has been toggled off, and a
     *                                                   service instanceexists for a given service.
     * @throws InvalidServiceException If an instance passed in the `services` configuration is invalid for the
     *                                 plugin manager.
     * @throws CyclicAliasException If the configuration contains aliases targeting themselves.
     */
    public function configure(array $config): static
    {
        if (isset($config['services'])) {
            foreach ($config['services'] as $service) {
                $this->validate($service);
            }
        }
        // phpcs:disable SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable
        /** @var ServiceManagerConfiguration $config */
        $this->plugins->configure($config);
        // phpcs:enable SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable
        return $this;
    }
    /**
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param string|class-string<InstanceType> $name
     * @param InstanceType $service
     */
    public function set_service(string $name, mixed $service): void
    {
        $this->validate($service);
        $this->plugins->set_service($name, $service);
    }
    /**
     * {@inheritDoc}
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            if (!$this->auto_add_invokable_class || !class_exists($id)) {
                throw new Exception\Service_Not_Found_Exception(sprintf('A plugin by the name "%s" was not found in the plugin manager %s', $id, static::class));
            }
            $this->plugins->set_factory($id, Factory\Invokable_Factory::class);
        }
        $instance = $this->plugins->get($id);
        $this->validate($instance);
        return $instance;
    }
    /**
     * {@inheritDoc}
     */
    public function has(string $id): bool
    {
        return $this->plugins->has($id);
    }
    /**
     * {@inheritDoc}
     */
    public function build(string $name, ?array $options = null): mixed
    {
        $plugin = $this->plugins->build($name, $options);
        $this->validate($plugin);
        return $plugin;
    }
    /**
     * Add an alias.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @throws ContainerModificationsNotAllowedException If $alias already
     *     exists as a service and overrides are disallowed.
     */
    public function set_alias(string $alias, string $target): void
    {
        $this->plugins->set_alias($alias, $target);
    }
    /**
     * Add an invokable class mapping.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param null|string $class Class to which to map; if omitted, $name is
     *     assumed.
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_invokable_class(string $name, string|null $class = null): void
    {
        $this->plugins->set_invokable_class($name, $class);
    }
    /**
     * Specify a factory for a given service name.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param class-string<FactoryInterface>|FactoryCallable|FactoryInterface $factory
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_factory(string $name, string|callable|Factory\Factory_Interface $factory): void
    {
        $this->plugins->set_factory($name, $factory);
    }
    /**
     * Create a lazy service mapping to a class.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param string|class-string $name Service name to map
     * @param null|class-string $class Class to which to map; if not provided, $name
     *     will be used for the mapping.
     */
    public function map_lazy_service(string $name, string|null $class = null): void
    {
        $this->plugins->map_lazy_service($name, $class);
    }
    /**
     * Add an abstract factory for resolving services.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param string|AbstractFactoryInterface $factory Abstract factory
     *     instance or class name.
     * @psalm-param class-string<AbstractFactoryInterface>|AbstractFactoryInterface $factory
     */
    public function add_abstract_factory(string|Abstract_Factory_Interface $factory): void
    {
        $this->plugins->add_abstract_factory($factory);
    }
    /**
     * Add a delegator for a given service.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param string $name Service name
     * @param string|callable|DelegatorFactoryInterface $factory Delegator factory to assign.
     * @psalm-param class-string<DelegatorFactoryInterface>|DelegatorCallable $factory
     */
    public function add_delegator(string $name, string|callable|Delegator_Factory_Interface $factory): void
    {
        $this->plugins->add_delegator($name, $factory);
    }
    /**
     * Add an initializer.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @psalm-param class-string<InitializerInterface>|InitializerCallable|InitializerInterface $initializer
     */
    public function add_initializer(string|callable|Initializer_Interface $initializer): void
    {
        $this->plugins->add_initializer($initializer);
    }
    /**
     * Add a service sharing rule.
     *
     * @deprecated Please use {@see AbstractPluginManager::configure()} instead.
     *
     * @param bool $flag Whether or not the service should be shared.
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_shared(string $name, bool $flag): void
    {
        $this->plugins->set_shared($name, $flag);
    }
    public function get_allow_override(): bool
    {
        return $this->plugins->get_allow_override();
    }
    public function set_allow_override(bool $flag): void
    {
        $this->plugins->set_allow_override($flag);
    }
}