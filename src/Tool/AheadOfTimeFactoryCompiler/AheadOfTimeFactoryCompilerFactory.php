<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler;

use function assert;
use Laminas\Service_Manager\Tool\Factory_Creator_Interface;
use Psr\Container\Container_Interface;
final class Ahead_Of_Time_Factory_Compiler_Factory
{
    public function __invoke(Container_Interface $container): Ahead_Of_Time_Factory_Compiler_Interface
    {
        $creator = $container->get(Factory_Creator_Interface::class);
        assert($creator instanceof Factory_Creator_Interface);
        return new Ahead_Of_Time_Factory_Compiler($creator);
    }
}