<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Command;

use function assert;
use function class_exists;
use function is_string;
use Laminas\Service_Manager\Exception;
use Laminas\Service_Manager\Tool\Factory_Creator_Interface;
use function sprintf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @internal CLI commands are not meant to be used in any upstream projects other than via `laminas-cli`.
 */
final class Factory_Creator_Command extends Command
{
    public const NAME = 'servicemanager:generate-factory-for-class';
    public function __construct(private readonly Factory_Creator_Interface $factory_creator)
    {
        parent::__construct(self::NAME);
    }
    protected function configure(): void
    {
        $this->add_argument('className', Input_Argument::REQUIRED, 'Name of the class to reflect and for which to generate a factory.');
        $this->set_description('Generates to STDOUT a factory for creating the specified class; this may then' . ' be added to your application, and configured as a factory for the class.');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $class_name = $input->get_argument('className');
        assert(is_string($class_name));
        if (!class_exists($class_name)) {
            $output->writeln(sprintf('<error>Class "%s" does not exist or could not be autoloaded.</error>', $class_name));
            return self::FAILURE;
        }
        try {
            $factory = $this->factory_creator->create_factory($class_name);
        } catch (Exception\InvalidArgumentException $e) {
            $output->writeln(sprintf('<error>Unable to create factory for "%s": %s</error>', $class_name, $e->get_message()));
            return self::FAILURE;
        }
        $output->writeln($factory);
        return self::SUCCESS;
    }
}