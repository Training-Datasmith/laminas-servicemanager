<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler;

final class Ahead_Of_Time_Compiled_Factory
{
    /**
     * @internal
     *
     * @param class-string     $fullyQualifiedClassName
     * @param non-empty-string $containerConfigurationKey
     * @param non-empty-string $generatedFactory
     */
    public function __construct(public string $fully_qualified_class_name, public string $container_configuration_key, public string $generated_factory)
    {
    }
}