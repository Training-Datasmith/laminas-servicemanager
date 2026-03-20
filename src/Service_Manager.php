<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function class_exists;
use Exception;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use Laminas\Service_Manager\Exception\Container_Modifications_Not_Allowed_Exception;
use Laminas\Service_Manager\Exception\Cyclic_Alias_Exception;
use Laminas\Service_Manager\Exception\InvalidArgumentException;
use Laminas\Service_Manager\Exception\Invalid_Service_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Created_Exception;
use Laminas\Service_Manager\Exception\Service_Not_Found_Exception;
use Laminas\Service_Manager\Factory\Abstract_Factory_Interface;
use Laminas\Service_Manager\Factory\Delegator_Factory_Interface;
use Laminas\Service_Manager\Factory\Factory_Interface;
use Laminas\Service_Manager\Factory\Invokable_Factory;
use Laminas\Service_Manager\Initializer\Initializer_Interface;
use Laminas\Service_Manager\Proxy\Lazy_Service_Factory;
use Laminas\Stdlib\Array_Utils;
use Proxy_Manager\Configuration as ProxyConfiguration;
use Proxy_Manager\Factory\Lazy_Loading_Value_Holder_Factory;
use Proxy_Manager\File_Locator\File_Locator;
use Proxy_Manager\Generator_Strategy\Evaluating_Generator_Strategy;
use Proxy_Manager\Generator_Strategy\File_Writer_Generator_Strategy;
use Psr\Container\Container_Exception_Interface;
use Psr\Container\Container_Interface;
use function spl_autoload_register;
use function spl_object_hash;
use function sprintf;
/**
 * Service Manager.
 *
 * Default implementation of the ServiceLocatorInterface, providing capabilities
 * for object creation via:
 *
 * - factories
 * - abstract factories
 * - delegator factories
 * - lazy service factories (generated proxies)
 * - initializers (interface injection)
 *
 * It also provides the ability to inject specific service instances and to
 * define aliases.
 *
 * @see ContainerInterface
 * @see DelegatorFactoryInterface
 * @see AbstractFactoryInterface
 * @see FactoryInterface
 *
 * @psalm-type AbstractFactoriesConfiguration = array<
 *      array-key,
 *      class-string<AbstractFactoryInterface>|AbstractFactoryInterface
 * >
 * @psalm-type DelegatorCallable = callable(ContainerInterface,string,callable():mixed,array<mixed>|null):mixed
 * @psalm-type DelegatorsConfiguration = array<
 *      string,
 *      array<
 *          array-key,
 *          class-string<DelegatorFactoryInterface>
 *          |class-string<object&DelegatorCallable>
 *          |DelegatorFactoryInterface
 *          |DelegatorCallable
 *      >
 * >
 * @psalm-type FactoryCallable = callable(ContainerInterface,string,array<mixed>|null):mixed
 * @psalm-type FactoriesConfiguration = array<
 *      string,
 *      class-string<FactoryInterface>|class-string<object&FactoryCallable>|FactoryInterface|FactoryCallable
 * >
 * @psalm-type InitializerCallable = callable(ContainerInterface,mixed):void
 * @psalm-type InitializersConfiguration = array<
 *      array-key,
 *      class-string<InitializerInterface>|class-string<object&InitializerCallable>|InitializerInterface|InitializerCallable
 * >
 * @psalm-type LazyServicesConfiguration = array{
 *      class_map?:array<string,class-string>,
 *      proxies_namespace?:non-empty-string,
 *      proxies_target_dir?:non-empty-string,
 *      write_proxy_files?:bool
 * }
 * @psalm-type ServiceManagerConfiguration = array{
 *     abstract_factories?: AbstractFactoriesConfiguration,
 *     aliases?: array<string,string>,
 *     delegators?: DelegatorsConfiguration,
 *     factories?: FactoriesConfiguration,
 *     initializers?: InitializersConfiguration,
 *     invokables?: array<string,class-string>,
 *     lazy_services?: LazyServicesConfiguration,
 *     services?: array<string,mixed>,
 *     shared?:array<string,bool>,
 *     shared_by_default?: bool,
 *     ...<string, mixed>,
 * }
 *
 * @final Will be marked as final with v5.0.0
 */
class Service_Manager implements Service_Locator_Interface
{
    /** @var AbstractFactoryInterface[] */
    protected array $abstract_factories = [];
    /**
     * A list of aliases
     *
     * Should map one alias to a service name, or another alias (aliases are recursively resolved)
     *
     * @var array<string,string>
     */
    protected array $aliases = [];
    /**
     * Whether or not changes may be made to this instance.
     */
    protected bool $allow_override = false;
    protected Container_Interface $creation_context;
    /** @var DelegatorsConfiguration */
    protected array $delegators = [];
    /**
     * A list of factories (either as string name or callable)
     *
     * @var FactoriesConfiguration
     */
    protected array $factories = [];
    /** @var list<InitializerInterface|InitializerCallable> */
    protected array $initializers = [];
    /** @var LazyServicesConfiguration */
    protected array $lazy_services = [];
    private ?Lazy_Service_Factory $lazy_services_delegator = null;
    /**
     * A list of already loaded services (this act as a local cache)
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
     * @var array<string,bool>
     */
    protected array $shared = [];
    /**
     * Should the services be shared by default?
     */
    protected bool $shared_by_default = true;
    /**
     * Service manager was already configured?
     */
    protected bool $configured = false;
    /**
     * Cached abstract factories from string.
     *
     * @var array<class-string<AbstractFactoryInterface>,AbstractFactoryInterface>
     */
    private array $cached_abstract_factories = [];
    /**
     * See {@see \Laminas\ServiceManager\ServiceManager::configure()} for details
     * on what $config accepts.
     *
     * @param ServiceManagerConfiguration $config
     */
    public function __construct(array $config = [], Container_Interface|null $creation_context = null)
    {
        $this->creation_context = $creation_context ?? $this;
        $this->configure($config);
    }
    /**
     * Retrieve a service by identifier, using the shared instance cache where possible.
     *
     * Resolution order: shared cache → static service/factory → abstract factories.
     * Aliases are resolved before cache lookup.
     *
     * @param string $id The service name or class-string to retrieve
     * @return mixed The service instance
     * @throws Service_Not_Found_Exception   If no factory can satisfy $id.
     * @throws Service_Not_Created_Exception If the factory throws during construction.
     * @complexity O(1) for cached services; O(n) for abstract-factory resolution
     *             where n is the number of registered abstract factories.
     * @see build() For uncached construction with per-call options.
     */
    public function get(string $id): mixed
    {
        // We start by checking if we have cached the requested service;
        // this is the fastest method.
        if (isset($this->services[$id])) {
            /** @psalm-suppress MixedReturnStatement Yes indeed, service managers can return mixed. */
            return $this->services[$id];
        }
        // Determine if the service should be shared.
        $shared_service = $this->shared[$id] ?? $this->shared_by_default;
        // We achieve better performance if we can let all alias
        // considerations out.
        if (!$this->aliases) {
            $service = $this->do_create($id);
            // Cache the service for later, if it is supposed to be shared.
            if ($shared_service) {
                $this->services[$id] = $service;
            }
            /** @psalm-suppress MixedReturnStatement Yes indeed, service managers can return mixed. */
            return $service;
        }
        // We now deal with requests which may be aliases.
        $resolved_name = $this->aliases[$id] ?? $id;
        // Update shared service information as we checked if the alias was shared before.
        if ($resolved_name !== $id) {
            $shared_service = $this->shared[$resolved_name] ?? $shared_service;
        }
        // The following is only true if the requested service is a shared alias.
        $shared_alias = $shared_service && isset($this->services[$resolved_name]);
        // If the alias is configured as a shared service, we are done.
        if ($shared_alias) {
            $this->services[$id] = $this->services[$resolved_name];
            /** @psalm-suppress MixedReturnStatement Yes indeed, service managers can return mixed. */
            return $this->services[$resolved_name];
        }
        // At this point, we have to create the object.
        // We use the resolved name for that.
        $service = $this->do_create($resolved_name);
        // Cache the object for later, if it is supposed to be shared.
        if ($shared_service) {
            $this->services[$resolved_name] = $service;
            $this->services[$id] = $service;
        }
        /** @psalm-suppress MixedReturnStatement Yes indeed, service managers can return mixed. */
        return $service;
    }
    /**
     * Create a fresh, uncached service instance with optional per-call options.
     *
     * Does not consult or populate the shared instance cache. The alias table
     * is resolved before factory lookup.
     *
     * @param string            $name    The service name or class-string to construct
     * @param array<mixed>|null $options Optional construction-time configuration
     * @return mixed The newly constructed service instance
     * @throws Service_Not_Found_Exception   If no factory can satisfy $name.
     * @throws Service_Not_Created_Exception If the factory throws during construction.
     * @complexity O(n) where n is the number of registered abstract factories (worst case)
     * @see get() For cached service retrieval.
     */
    public function build(string $name, ?array $options = null): mixed
    {
        // We never cache when using "build".
        $name = $this->aliases[$name] ?? $name;
        /** @psalm-suppress MixedReturnStatement Yes indeed, service managers can return mixed. */
        return $this->do_create($name, $options);
    }
    /**
     * Check whether the container can produce a service for the given identifier.
     *
     * Checks static services and registered factories first (O(1)), then falls
     * through to abstract factories (O(n)). Alias names are resolved transparently.
     *
     * @param string $id The service name or class-string to check
     * @return bool True if the container can construct or return the service
     * @complexity O(1) for named services and factories; O(n) for abstract factory fallback
     * @see get() To actually retrieve the service.
     */
    public function has(string $id): bool
    {
        // Check static services and factories first to speedup the most common requests.
        if ($this->static_service_or_factory_can_create($id)) {
            return true;
        }
        return $this->abstract_factory_can_create($id);
    }
    /**
     * Indicate whether or not the instance is immutable.
     */
    public function set_allow_override(bool $flag): void
    {
        $this->allow_override = $flag;
    }
    /**
     * Retrieve the flag indicating immutability status.
     */
    public function get_allow_override(): bool
    {
        return $this->allow_override;
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
        // This is a bulk update/initial configuration,
        // so we check all definitions up front.
        $this->validate_service_names($config);
        if (isset($config['services'])) {
            $this->services = $config['services'] + $this->services;
        }
        if (isset($config['invokables']) && $config['invokables'] !== []) {
            $new_aliases = $this->create_aliases_and_factories_for_invokables($config['invokables']);
            // override existing aliases with those created by invokables to ensure
            // that they are still present after merging aliases later on
            $config['aliases'] = $new_aliases + ($config['aliases'] ?? []);
        }
        if (isset($config['factories'])) {
            $this->factories = $config['factories'] + $this->factories;
        }
        if (isset($config['delegators'])) {
            $this->merge_delegators($config['delegators']);
        }
        if (isset($config['shared'])) {
            $this->shared = $config['shared'] + $this->shared;
        }
        if (isset($config['aliases']) && $config['aliases'] !== []) {
            $this->aliases = $config['aliases'] + $this->aliases;
            $this->map_aliases_to_targets();
        } elseif (!$this->configured && $this->aliases !== []) {
            $this->map_aliases_to_targets();
        }
        if (isset($config['shared_by_default'])) {
            $this->shared_by_default = $config['shared_by_default'];
        }
        // If lazy service configuration was provided, reset the lazy services
        // delegator factory.
        if (isset($config['lazy_services']) && $config['lazy_services'] !== []) {
            /** @psalm-suppress MixedPropertyTypeCoercion */
            $this->lazy_services = Array_Utils::merge($this->lazy_services, $config['lazy_services']);
            $this->lazy_services_delegator = null;
        }
        // For abstract factories and initializers, we always directly
        // instantiate them to avoid checks during service construction.
        if (isset($config['abstract_factories'])) {
            $abstract_factories = $config['abstract_factories'];
            // $key not needed, but foreach is faster than foreach + array_values.
            foreach ($abstract_factories as $abstract_factory) {
                $this->resolve_abstract_factory_instance($abstract_factory);
            }
        }
        if (isset($config['initializers'])) {
            $this->resolve_initializers($config['initializers']);
        }
        $this->configured = true;
        return $this;
    }
    /**
     * Add an alias.
     *
     * @throws ContainerModificationsNotAllowedException If $alias already
     *     exists as a service and overrides are disallowed.
     */
    public function set_alias(string $alias, string $target): void
    {
        if (isset($this->services[$alias]) && !$this->allow_override) {
            throw Container_Modifications_Not_Allowed_Exception::from_existing_service($alias);
        }
        $this->map_alias_to_target($alias, $target);
    }
    /**
     * Add an invokable class mapping.
     *
     * @param string $name Service name
     * @param null|string $class Class to which to map; if omitted, $name is
     *     assumed.
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_invokable_class(string $name, ?string $class = null): void
    {
        if (isset($this->services[$name]) && !$this->allow_override) {
            throw Container_Modifications_Not_Allowed_Exception::from_existing_service($name);
        }
        $this->create_aliases_and_factories_for_invokables([$name => $class ?? $name]);
    }
    /**
     * Specify a factory for a given service name.
     *
     * @param string $name Service name
     * @param string|callable|FactoryInterface $factory  Factory to which to map.
     * @phpcs:disable Generic.Files.LineLength.TooLong
     * @psalm-param class-string<FactoryInterface>|class-string<object&FactoryCallable>|FactoryCallable|FactoryInterface $factory
     * @phpcs:enable Generic.Files.LineLength.TooLong
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_factory(string $name, string|callable|Factory_Interface $factory): void
    {
        if (isset($this->services[$name]) && !$this->allow_override) {
            throw Container_Modifications_Not_Allowed_Exception::from_existing_service($name);
        }
        $this->factories[$name] = $factory;
    }
    /**
     * Create a lazy service mapping to a class.
     *
     * @param string|class-string $name Service name to map
     * @param null|class-string $class Class to which to map; if not provided, $name
     *     will be used for the mapping.
     */
    public function map_lazy_service(string $name, ?string $class = null): void
    {
        $target_class_name = $class ?? $name;
        if (!class_exists($target_class_name)) {
            $message = sprintf('Provided service name "%s" must be a `class-string`.', $name);
            if ($class !== null) {
                $message = sprintf('Provided service name "%s" must target to a `class-string`. "%s" provided.', $name, $class);
            }
            throw new InvalidArgumentException($message);
        }
        $this->configure(['lazy_services' => ['class_map' => [$name => $target_class_name]]]);
    }
    /**
     * Add an abstract factory for resolving services.
     *
     * @param string|AbstractFactoryInterface $factory Abstract factory
     *     instance or class name.
     * @psalm-param class-string<AbstractFactoryInterface>|AbstractFactoryInterface $factory
     */
    public function add_abstract_factory(string|Abstract_Factory_Interface $factory): void
    {
        $this->resolve_abstract_factory_instance($factory);
    }
    /**
     * Add a delegator for a given service.
     *
     * @param string $name Service name
     * @param string|callable|DelegatorFactoryInterface $factory Delegator
     *     factory to assign.
     * @phpcs:disable Generic.Files.LineLength.TooLong
     * @psalm-param class-string<DelegatorFactoryInterface>|class-string<object&DelegatorCallable>|DelegatorCallable|DelegatorFactoryInterface $factory
     * @phpcs:enable Generic.Files.LineLength.TooLong
     */
    public function add_delegator(string $name, string|callable|Delegator_Factory_Interface $factory): void
    {
        $this->configure(['delegators' => [$name => [$factory]]]);
    }
    /**
     * Add an initializer.
     *
     * @phpcs:disable Generic.Files.LineLength.TooLong
     * @psalm-param class-string<InitializerInterface>|class-string<object&InitializerCallable>|InitializerCallable|InitializerInterface $initializer
     * @phpcs:enable Generic.Files.LineLength.TooLong
     */
    public function add_initializer(string|callable|Initializer_Interface $initializer): void
    {
        $this->configure(['initializers' => [$initializer]]);
    }
    /**
     * Map a service.
     *
     * @param string $name Service name
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_service(string $name, mixed $service): void
    {
        if (isset($this->services[$name]) && !$this->allow_override) {
            throw Container_Modifications_Not_Allowed_Exception::from_existing_service($name);
        }
        $this->services[$name] = $service;
    }
    /**
     * Add a service sharing rule.
     *
     * @param string $name Service name
     * @param bool $flag Whether or not the service should be shared.
     * @throws ContainerModificationsNotAllowedException If $name already
     *     exists as a service and overrides are disallowed.
     */
    public function set_shared(string $name, bool $flag): void
    {
        if (isset($this->services[$name]) && !$this->allow_override) {
            throw Container_Modifications_Not_Allowed_Exception::from_existing_service($name);
        }
        $this->shared[$name] = $flag;
    }
    /**
     * Instantiate initializers for to avoid checks during service construction.
     *
     * @param InitializersConfiguration $initializers
     */
    private function resolve_initializers(array $initializers): void
    {
        $resolved = [];
        foreach ($initializers as $initializer) {
            if (is_string($initializer) && class_exists($initializer)) {
                /**
                 * @psalm-suppress MixedMethodCall We are calling an unknown initializer.
                 *                                 We have to trust that the class is instantiable.
                 */
                $initializer = new $initializer();
            }
            if (is_callable($initializer)) {
                /** @psalm-var InitializerCallable $initializer */
                $resolved[] = $initializer;
                continue;
            }
            throw InvalidArgumentException::from_invalid_initializer($initializer);
        }
        $this->initializers = array_merge($this->initializers, $resolved);
    }
    /**
     * Get a factory for the given service name
     *
     * @return FactoryCallable|FactoryInterface
     * @throws ServiceNotFoundException In case that the service creation strategy based on factories
     *                                  did not find any capable factory.
     */
    private function get_factory(string $name): callable|Factory_Interface
    {
        $factory = $this->factories[$name] ?? null;
        $lazy_loaded = false;
        if (is_string($factory) && class_exists($factory)) {
            /** @psalm-suppress MixedMethodCall We have to trust that the factory is instantiable. */
            $factory = new $factory();
            $lazy_loaded = true;
        }
        if (is_callable($factory)) {
            /** @psalm-var FactoryCallable $factory */
            if ($lazy_loaded) {
                $this->factories[$name] = $factory;
            }
            return $factory;
        }
        // Check abstract factories
        foreach ($this->abstract_factories as $abstract_factory) {
            if ($abstract_factory->can_create($this->creation_context, $name)) {
                return $abstract_factory;
            }
        }
        throw new Service_Not_Found_Exception(sprintf('Unable to resolve service "%s" to a factory; are you certain you provided it during configuration?', $name));
    }
    private function create_delegator_from_name(string $name, ?array $options = null): mixed
    {
        $creation_callback = function () use ($name, $options) {
            // Code is inlined for performance reason, instead of abstracting the creation
            $factory = $this->get_factory($name);
            return $factory($this->creation_context, $name, $options);
        };
        $initial_creation_context = $this->creation_context;
        $resolved_delegators = [];
        foreach ($this->delegators[$name] as $index => $delegator_factory) {
            /** @psalm-suppress ArgumentTypeCoercion https://github.com/vimeo/psalm/issues/9680 */
            $delegator_factory = $this->resolve_delegator_factory($delegator_factory);
            $resolved_delegators[$index] = $delegator_factory;
            $creation_callback = static fn(): mixed => $delegator_factory($initial_creation_context, $name, $creation_callback, $options);
        }
        $this->delegators[$name] = $resolved_delegators;
        return $creation_callback();
    }
    /**
     * Create a new instance with an already resolved name
     *
     * This is a highly performance sensitive method, do not modify if you have not benchmarked it carefully
     *
     * @throws ServiceNotFoundException If unable to resolve the service.
     * @throws ServiceNotCreatedException If an exception is raised when creating a service.
     * @throws ContainerExceptionInterface If any other error occurs.
     */
    private function do_create(string $resolved_name, ?array $options = null): mixed
    {
        try {
            if (!isset($this->delegators[$resolved_name])) {
                // Let's create the service by fetching the factory
                $factory = $this->get_factory($resolved_name);
                $service = $factory($this->creation_context, $resolved_name, $options);
            } else {
                $service = $this->create_delegator_from_name($resolved_name, $options);
            }
        } catch (Exception $exception) {
            throw new Service_Not_Created_Exception(sprintf('Service with name "%s" could not be created. Reason: %s', $resolved_name, $exception->get_message()), (int) $exception->get_code(), $exception);
        }
        foreach ($this->initializers as $initializer) {
            $initializer($this->creation_context, $service);
        }
        return $service;
    }
    /**
     * Create the lazy services delegator factory.
     *
     * Creates the lazy services delegator factory based on the lazy_services
     * configuration present.
     *
     * @throws ServiceNotCreatedException When the lazy service class_map configuration is missing.
     */
    private function create_lazy_service_delegator_factory(): Lazy_Service_Factory
    {
        if ($this->lazy_services_delegator) {
            return $this->lazy_services_delegator;
        }
        if (!isset($this->lazy_services['class_map'])) {
            throw new Service_Not_Created_Exception('Missing "class_map" config key in "lazy_services"');
        }
        $factory_config = new Proxy_Configuration();
        if (isset($this->lazy_services['proxies_namespace'])) {
            $factory_config->set_proxies_namespace($this->lazy_services['proxies_namespace']);
        }
        if (isset($this->lazy_services['proxies_target_dir'])) {
            $factory_config->set_proxies_target_dir($this->lazy_services['proxies_target_dir']);
        }
        if (!isset($this->lazy_services['write_proxy_files']) || !$this->lazy_services['write_proxy_files']) {
            $factory_config->set_generator_strategy(new Evaluating_Generator_Strategy());
        } else {
            $factory_config->set_generator_strategy(new File_Writer_Generator_Strategy(new File_Locator($factory_config->get_proxies_target_dir())));
        }
        spl_autoload_register($factory_config->get_proxy_autoloader());
        $this->lazy_services_delegator = new Lazy_Service_Factory(new Lazy_Loading_Value_Holder_Factory($factory_config), $this->lazy_services['class_map']);
        return $this->lazy_services_delegator;
    }
    /**
     * Merge delegators avoiding multiple same delegators for the same service.
     * It works with strings and class instances.
     * It's not possible to de-duple anonymous functions
     *
     * @param DelegatorsConfiguration $config
     * @return DelegatorsConfiguration
     */
    private function merge_delegators(array $config): array
    {
        foreach ($config as $key => $delegators) {
            if (!array_key_exists($key, $this->delegators)) {
                $this->delegators[$key] = [];
            }
            foreach ($delegators as $delegator) {
                if (!in_array($delegator, $this->delegators[$key], true)) {
                    $this->delegators[$key][] = $delegator;
                }
            }
        }
        return $this->delegators;
    }
    /**
     * Create aliases and factories for invokable classes.
     *
     * If an invokable service name does not match the class it maps to, this
     * creates an alias to the class (which will later be mapped as an
     * invokable factory). The newly created aliases will be returned as an array.
     *
     * @param array<string,string> $invokables
     * @return array<string,string>
     */
    private function create_aliases_and_factories_for_invokables(array $invokables): array
    {
        $new_aliases = [];
        foreach ($invokables as $name => $class) {
            $this->factories[$class] = Invokable_Factory::class;
            if ($name !== $class) {
                $this->aliases[$name] = $class;
                $new_aliases[$name] = $class;
            }
        }
        return $new_aliases;
    }
    /**
     * Determine if a service for any name provided by a service
     * manager configuration(services, aliases, factories, ...)
     * already exists, and if it exists, determine if is it allowed
     * to get overriden.
     *
     * Validation in the context of this class means, that for
     * a given service name we do not have a service instance
     * in the cache OR override is explicitly allowed.
     *
     * @param ServiceManagerConfiguration $config
     * @throws ContainerModificationsNotAllowedException If any
     *     service key is invalid.
     */
    private function validate_service_names(array $config): void
    {
        if ($this->allow_override || !$this->configured) {
            return;
        }
        if (isset($config['services'])) {
            foreach (array_keys($config['services']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
        if (isset($config['aliases']) && is_array($config['aliases'])) {
            /** @psalm-var string $service */
            foreach (array_keys($config['aliases']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
        if (isset($config['invokables']) && is_array($config['invokables'])) {
            /** @psalm-var string $service */
            foreach (array_keys($config['invokables']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
        if (isset($config['factories']) && is_array($config['factories'])) {
            /** @psalm-var string $service */
            foreach (array_keys($config['factories']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
        if (isset($config['delegators']) && is_array($config['delegators'])) {
            /** @psalm-var string $service */
            foreach (array_keys($config['delegators']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
        if (isset($config['shared']) && is_array($config['shared'])) {
            /** @psalm-var string $service */
            foreach (array_keys($config['shared']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
        if (isset($config['lazy_services']['class_map']) && is_array($config['lazy_services']['class_map'])) {
            /** @psalm-var string $service */
            foreach (array_keys($config['lazy_services']['class_map']) as $service) {
                if (isset($this->services[$service])) {
                    throw Container_Modifications_Not_Allowed_Exception::from_existing_service($service);
                }
            }
        }
    }
    /**
     * Assuming that the alias name is valid (see above) resolve/add it.
     *
     * This is done differently from bulk mapping aliases for performance reasons, as the
     * algorithms for mapping a single item efficiently are different from those of mapping
     * many.
     */
    private function map_alias_to_target(string $alias, string $target): void
    {
        // $target is either an alias or something else
        // if it is an alias, resolve it
        $this->aliases[$alias] = $this->aliases[$target] ?? $target;
        // a self-referencing alias indicates a cycle
        if ($alias === $this->aliases[$alias]) {
            throw Cyclic_Alias_Exception::from_cyclic_alias($alias, $this->aliases);
        }
        // finally we have to check if existing incomplete alias definitions
        // exist which can get resolved by the new alias
        if (in_array($alias, $this->aliases)) {
            $r = array_intersect($this->aliases, [$alias]);
            // found some, resolve them
            foreach ($r as $name => $service) {
                $this->aliases[$name] = $target;
            }
        }
    }
    /**
     * Assuming that all provided alias keys are valid resolve them.
     *
     * This function maps $this->aliases in place.
     *
     * This algorithm is an adaptated version of Tarjans Strongly
     * Connected Components. Instead of returning the strongly
     * connected components (i.e. cycles in our case), we throw.
     * If nodes are not strongly connected (i.e. resolvable in
     * our case), they get resolved.
     *
     * This algorithm is fast for mass updates through configure().
     * It is not appropriate if just a single alias is added.
     *
     * @see mapAliasToTarget above
     */
    private function map_aliases_to_targets(): void
    {
        $tagged = [];
        foreach ($this->aliases as $alias => $target) {
            if (isset($tagged[$alias])) {
                continue;
            }
            $t_cursor = $this->aliases[$alias];
            $a_cursor = $alias;
            if ($a_cursor === $t_cursor) {
                throw Cyclic_Alias_Exception::from_cyclic_alias($alias, $this->aliases);
            }
            if (!isset($this->aliases[$t_cursor])) {
                continue;
            }
            $stack = [];
            while (isset($this->aliases[$t_cursor])) {
                $stack[] = $a_cursor;
                if ($a_cursor === $this->aliases[$t_cursor]) {
                    throw Cyclic_Alias_Exception::from_cyclic_alias($alias, $this->aliases);
                }
                $a_cursor = $t_cursor;
                $t_cursor = $this->aliases[$t_cursor];
            }
            $tagged[$a_cursor] = true;
            foreach ($stack as $alias) {
                if ($alias === $t_cursor) {
                    throw Cyclic_Alias_Exception::from_cyclic_alias($alias, $this->aliases);
                }
                $this->aliases[$alias] = $t_cursor;
                $tagged[$alias] = true;
            }
        }
    }
    /**
     * Instantiate abstract factories in order to avoid checks during service construction.
     *
     * @param class-string<AbstractFactoryInterface>|AbstractFactoryInterface $abstractFactory
     */
    private function resolve_abstract_factory_instance(string|Abstract_Factory_Interface $abstract_factory): void
    {
        if (is_string($abstract_factory)) {
            // Cached string factory name
            if (!isset($this->cached_abstract_factories[$abstract_factory])) {
                $this->cached_abstract_factories[$abstract_factory] = new $abstract_factory();
            }
            $abstract_factory = $this->cached_abstract_factories[$abstract_factory];
        }
        $abstract_factory_obj_hash = spl_object_hash($abstract_factory);
        $this->abstract_factories[$abstract_factory_obj_hash] = $abstract_factory;
    }
    /**
     * Check if a static service or factory exists for the given name.
     */
    private function static_service_or_factory_can_create(string $name): bool
    {
        if (isset($this->services[$name]) || isset($this->factories[$name])) {
            return true;
        }
        $resolved_name = $this->aliases[$name] ?? $name;
        if ($resolved_name !== $name) {
            return $this->static_service_or_factory_can_create($resolved_name);
        }
        return false;
    }
    /**
     * Check if an abstract factory exists that can create a service for the given name.
     */
    private function abstract_factory_can_create(string $name): bool
    {
        foreach ($this->abstract_factories as $abstract_factory) {
            if ($abstract_factory->can_create($this->creation_context, $name)) {
                return true;
            }
        }
        $resolved_name = $this->aliases[$name] ?? $name;
        if ($resolved_name !== $name) {
            return $this->abstract_factory_can_create($resolved_name);
        }
        return false;
    }
    /**
     * @param class-string<DelegatorFactoryInterface>|DelegatorCallable|DelegatorFactoryInterface $delegatorFactory
     * @return DelegatorCallable
     */
    private function resolve_delegator_factory(Delegator_Factory_Interface|string|callable $delegator_factory): callable
    {
        if ($delegator_factory === Lazy_Service_Factory::class) {
            return $this->create_lazy_service_delegator_factory();
        }
        if (is_callable($delegator_factory)) {
            return $delegator_factory;
        }
        if (is_string($delegator_factory)) {
            return new $delegator_factory();
        }
        return $delegator_factory;
    }
}