<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool;

interface Factory_Creator_Interface
{
    /**
     * @param class-string $className
     * @param array<string,string> $aliases
     * @return non-empty-string
     */
    public function create_factory(string $class_name, array $aliases = []): string;
}