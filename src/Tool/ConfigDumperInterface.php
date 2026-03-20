<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool;

use Laminas\Service_Manager\Exception\InvalidArgumentException;
interface Config_Dumper_Interface
{
    /**
     * @param array<string,mixed> $config
     * @param class-string $className
     * @return array<string,mixed>
     * @throws InvalidArgumentException For unsupported class-string.
     */
    public function create_dependency_config(array $config, string $class_name, bool $ignore_unresolved = false): array;
    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     * @throws InvalidArgumentException If ConfigAbstractFactory configuration
     *     value is not an array.
     */
    public function create_factory_mappings_from_config(array $config): array;
    /**
     * @param array<string,mixed> $config
     * @param class-string $className
     * @return array<string,mixed>
     */
    public function create_factory_mappings(array $config, string $class_name): array;
    /**
     * @return non-empty-string
     */
    public function dump_config_file(array $config): string;
}