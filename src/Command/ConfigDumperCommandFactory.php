<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Command;

use function assert;
use Laminas\Service_Manager\Tool\Config_Dumper_Interface;
use Psr\Container\Container_Interface;
/**
 * @internal Factories are not meant to be used in any upstream projects.
 */
final class Config_Dumper_Command_Factory
{
    public function __invoke(Container_Interface $container): Config_Dumper_Command
    {
        $dumper = $container->get(Config_Dumper_Interface::class);
        assert($dumper instanceof Config_Dumper_Interface);
        return new Config_Dumper_Command($dumper);
    }
}