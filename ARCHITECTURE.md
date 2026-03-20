# Architecture: laminas-servicemanager

## Purpose

laminas-servicemanager is a PSR-11-compliant dependency injection container with:
- Named service registration (factories, invokables, instances, aliases)
- Abstract factories for catch-all service creation
- Delegator factories for decorating services post-construction
- Lazy service proxies via ocramius/proxy-manager
- Initializers for interface-injection post-construction
- Ahead-of-time (AOT) factory compilation for performance

## Directory Structure

```
src/
  Service_Manager.php                   # Primary container implementation (PSR-11 + build())
  Service_Locator_Interface.php         # Extends ContainerInterface with build() for uncached creation
  Plugin_Manager_Interface.php          # Sub-interface for typed plugin managers
  Abstract_Plugin_Manager.php           # Base for typed sub-containers (controller managers, etc.)
  Abstract_Single_Instance_Plugin_Manager.php  # Plugin manager variant: singleton per name
  Config_Provider.php                   # Mezzio/Expressive config provider
  Module.php                            # Laminas MVC module integration
  Factory/
    Factory_Interface.php               # Contract: __invoke(container, name, options)
    Abstract_Factory_Interface.php      # Adds can_create() for catch-all factories
    Delegator_Factory_Interface.php     # Wraps existing factories for decoration
    Invokable_Factory.php               # No-arg or single-array-arg constructor factory
  Initializer/
    Initializer_Interface.php           # Called on every created service for interface injection
  AbstractFactory/
    Config_Abstract_Factory.php         # Creates services from a config array key
    Reflection_Based_Abstract_Factory.php  # Auto-wires via PHP reflection (dev/testing only)
  Proxy/
    Lazy_Service_Factory.php            # Creates lazy proxy objects via ocramius/proxy-manager
  Exception/
    Exception_Interface.php             # Marker interface for all library exceptions
    Container_Modifications_Not_Allowed_Exception.php
    Cyclic_Alias_Exception.php
    Invalid_Argument_Exception.php
    Invalid_Service_Exception.php
    Runtime_Exception.php
    Service_Not_Created_Exception.php   # Factory threw or could not build service
    Service_Not_Found_Exception.php     # No factory found for requested name
  Command/                              # Symfony Console commands for factory generation
  Tool/                                 # Ahead-of-time compiler and config dumper utilities
  Test/
    Common_Plugin_Manager_Trait.php     # Shared test assertions for plugin manager tests
```

## Key Design Decisions

- **Shared by default**: All services are cached after first creation (`shared_by_default = true`).
  Use `build()` or set `shared = false` for non-singleton services.
- **Alias resolution**: Aliases are pre-resolved into a flat map at configure-time using a
  Tarjan-inspired algorithm, making alias lookup O(1) at service-retrieval time.
- **Lazy factory instantiation**: Factory class names are kept as strings and only instantiated
  on first use, reducing memory overhead for large container configurations.
- **Abstract factories are expensive**: Each registered abstract factory's `can_create()` is
  called for every cache-miss. Prefer named factories where possible.
- **Delegators**: Decorating a service requires registering a delegator — not subclassing
  the service. This keeps extension open/closed without modifying the factory.
- **AOT compilation**: For production, the `Ahead_Of_Time_Factory_Compiler` generates
  concrete factory classes, eliminating reflection overhead entirely.

## Extension Points

- Implement `Factory_Interface` for named service construction.
- Implement `Abstract_Factory_Interface` for convention-based (pattern-matched) creation.
- Implement `Delegator_Factory_Interface` to wrap/decorate any existing service.
- Implement `Initializer_Interface` for post-construction interface injection.
- Extend `Abstract_Plugin_Manager` to build a typed sub-container.

## Dependency Flow

```
Service_Manager implements Service_Locator_Interface (extends ContainerInterface)
  → delegates creation to: Factory_Interface | Abstract_Factory_Interface
  → wraps via: Delegator_Factory_Interface
  → post-processes via: Initializer_Interface
  → proxies via: Proxy\Lazy_Service_Factory → ocramius/proxy-manager
  → merges config via: Laminas\Stdlib\Array_Utils
```
