<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Command;

use function assert;
use function is_array;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use Laminas\Service_Manager\Config_Provider;
use Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler\Ahead_Of_Time_Factory_Compiler_Interface;
use Psr\Container\Container_Interface;
final class Ahead_Of_Time_Factory_Creator_Command_Factory
{
    public function __invoke(Container_Interface $container): Ahead_Of_Time_Factory_Creator_Command
    {
        $ahead_of_time_factory_compiler = $container->get(Ahead_Of_Time_Factory_Compiler_Interface::class);
        assert($ahead_of_time_factory_compiler instanceof Ahead_Of_Time_Factory_Compiler_Interface);
        $config = $container->has('config') ? $container->get('config') : [];
        if (!is_iterable($config)) {
            return new Ahead_Of_Time_Factory_Creator_Command([], '', $ahead_of_time_factory_compiler);
        }
        if (!is_array($config)) {
            $config = iterator_to_array($config);
        }
        $factory_target_path = $config[Config_Provider::CONFIGURATION_KEY_FACTORY_TARGET_PATH] ?? '';
        if (!is_string($factory_target_path)) {
            $factory_target_path = '';
        }
        return new Ahead_Of_Time_Factory_Creator_Command($config, $factory_target_path, $ahead_of_time_factory_compiler);
    }
}