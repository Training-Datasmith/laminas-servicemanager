<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler;

interface Ahead_Of_Time_Factory_Compiler_Interface
{
    /**
     * @return list<AheadOfTimeCompiledFactory>
     */
    public function compile(array $config): array;
}