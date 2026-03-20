<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

use function class_exists;
use Laminas\Service_Manager\Command\Ahead_Of_Time_Factory_Creator_Command;
use Laminas\Service_Manager\Command\Ahead_Of_Time_Factory_Creator_Command_Factory;
use Laminas\Service_Manager\Command\Config_Dumper_Command;
use Laminas\Service_Manager\Command\Config_Dumper_Command_Factory;
use Laminas\Service_Manager\Command\Factory_Creator_Command;
use Laminas\Service_Manager\Command\Factory_Creator_Command_Factory;
use Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler\Ahead_Of_Time_Factory_Compiler_Factory;
use Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler\Ahead_Of_Time_Factory_Compiler_Interface;
use Laminas\Service_Manager\Tool\Config_Dumper_Factory;
use Laminas\Service_Manager\Tool\Config_Dumper_Interface;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Constructor_Parameter_Resolver;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Constructor_Parameter_Resolver_Interface;
use Laminas\Service_Manager\Tool\Factory_Creator_Factory;
use Laminas\Service_Manager\Tool\Factory_Creator_Interface;
use Symfony\Component\Console\Command\Command;
/**
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
final class Config_Provider
{
    public const CONFIGURATION_KEY_FACTORY_TARGET_PATH = 'aot-factory-target-path';
    /**
     * @return array{dependencies: ServiceManagerConfiguration}&array<string,mixed>
     */
    public function __invoke(): array
    {
        return ['dependencies' => $this->get_service_dependencies(), 'laminas-cli' => $this->get_laminas_cli_dependencies()];
    }
    /**
     * @return ServiceManagerConfiguration
     */
    public function get_service_dependencies(): array
    {
        $factories = [Config_Dumper_Interface::class => Config_Dumper_Factory::class, Factory_Creator_Interface::class => Factory_Creator_Factory::class, Ahead_Of_Time_Factory_Compiler_Interface::class => Ahead_Of_Time_Factory_Compiler_Factory::class, Constructor_Parameter_Resolver_Interface::class => static fn(): Constructor_Parameter_Resolver_Interface => new Constructor_Parameter_Resolver()];
        if (class_exists(Command::class)) {
            $factories += [Ahead_Of_Time_Factory_Creator_Command::class => Ahead_Of_Time_Factory_Creator_Command_Factory::class, Config_Dumper_Command::class => Config_Dumper_Command_Factory::class, Factory_Creator_Command::class => Factory_Creator_Command_Factory::class];
        }
        return ['factories' => $factories];
    }
    /**
     * @return array<string,mixed>
     */
    private function get_laminas_cli_dependencies(): array
    {
        if (!class_exists(Command::class)) {
            return [];
        }
        return ['commands' => [Config_Dumper_Command::NAME => Config_Dumper_Command::class, Factory_Creator_Command::NAME => Factory_Creator_Command::class, Ahead_Of_Time_Factory_Creator_Command::NAME => Ahead_Of_Time_Factory_Creator_Command::class]];
    }
}