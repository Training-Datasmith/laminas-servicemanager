<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Command;

use function array_keys;
use function assert;
use function class_exists;
use function dirname;
use function file_exists;
use function file_put_contents;
use InvalidArgumentException;
use function is_array;
use function is_string;
use function is_writable;
use Laminas\Service_Manager\Exception;
use Laminas\Service_Manager\Tool\Config_Dumper_Interface;
use function sprintf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @internal CLI commands are not meant to be used in any upstream projects other than via `laminas-cli`.
 */
final class Config_Dumper_Command extends Command
{
    public const NAME = 'servicemanager:generate-deps-for-config-factory';
    public function __construct(private readonly Config_Dumper_Interface $config_dumper)
    {
        parent::__construct(self::NAME);
    }
    protected function configure(): void
    {
        $this->add_option('ignore-unresolved', 'i', Input_Option::VALUE_NONE, 'Ignore classes with unresolved direct dependencies.');
        $this->add_argument('configFile', Input_Argument::REQUIRED, 'Path to a config file for which to generate configuration. If the file does not exist, it will be created.' . ' If it does exist, it must return an array, and the file will be updated with new configuration.');
        $this->add_argument('class', Input_Argument::REQUIRED, 'Name of the class to reflect and for which to generate dependency configuration.');
        $this->set_description('Reads the provided configuration file (creating it if it does not exist),' . ' and injects it with ConfigAbstractFactory dependency configuration for' . ' the provided class name, writing the changes back to the file.');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $config_file = $input->get_argument('configFile');
        assert(is_string($config_file));
        try {
            $config_from_config_file = $this->get_config($config_file);
        } catch (InvalidArgumentException $exception) {
            $output->writeln(sprintf('<error>%s</error>', $exception->get_message()));
            return self::FAILURE;
        }
        $class = $input->get_argument('class');
        assert(is_string($class));
        if (!class_exists($class)) {
            $output->writeln(sprintf('<error>Class "%s" does not exist or could not be autoloaded.</error>', $class));
            return self::FAILURE;
        }
        try {
            $config = $this->config_dumper->create_dependency_config($config_from_config_file, $class, $input->has_option('ignore-unresolved'));
        } catch (Exception\InvalidArgumentException $exception) {
            $output->writeln(sprintf('<error>Unable to create config for "%s": %s</error>', $class, $exception->get_message()));
            return self::FAILURE;
        }
        file_put_contents($config_file, $this->config_dumper->dump_config_file($config));
        $output->writeln(sprintf('<info>[DONE]</info> Changes written to %s', $config_file));
        return self::SUCCESS;
    }
    /**
     * @return array<string,mixed>
     */
    private function get_config(string $config_file): array
    {
        if (file_exists($config_file)) {
            $config = require $config_file;
            $this->assert_configuration_is_map($config, $config_file);
            return $config;
        }
        if (!is_writable(dirname($config_file))) {
            throw new InvalidArgumentException(sprintf('Cannot create configuration at path "%s"; not writable.', $config_file));
        }
        return [];
    }
    /**
     * @psalm-assert array<string,mixed> $config
     */
    private function assert_configuration_is_map(mixed $config, string $config_file): void
    {
        if (!is_array($config)) {
            throw new InvalidArgumentException(sprintf('Configuration at path "%s" does not return an array.', $config_file));
        }
        if ($config === []) {
            return;
        }
        foreach (array_keys($config) as $key) {
            if (is_string($key)) {
                return;
            }
        }
        throw new InvalidArgumentException(sprintf('Configuration at path "%s" does not return a map of configuration keys.', $config_file));
    }
}