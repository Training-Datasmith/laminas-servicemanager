<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool;

use function class_exists;
use Mezzio\Application;
use Psr\Container\Container_Interface;
/**
 * @internal
 */
final class Config_Dumper_Factory
{
    public function __invoke(Container_Interface $container): Config_Dumper_Interface
    {
        if ($this->is_command_executed_in_mezzio_application($container)) {
            return new Config_Dumper($container, Config_Dumper::MEZZIO_CONTAINER_CONFIGURATION);
        }
        return new Config_Dumper($container, Config_Dumper::LAMINAS_MVC_SERVICEMANAGER_CONFIGURATION);
    }
    private function is_command_executed_in_mezzio_application(Container_Interface $container): bool
    {
        /**
         * @psalm-suppress UndefinedClass MixedArgument We can't require mezzio due to the amount of additional
         *                                              dependencies we would have to add here.
         */
        return class_exists(Application::class) && $container->has(Application::class);
    }
}