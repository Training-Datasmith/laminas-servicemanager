<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Command;

use function assert;
use Brick\Var_Exporter\Var_Exporter;
use function class_exists;
use function count;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_string;
use function is_writable;
use Laminas\Service_Manager\Config_Provider;
use Laminas\Service_Manager\Exception\RuntimeException;
use Laminas\Service_Manager\Factory\Factory_Interface;
use Laminas\Service_Manager\Service_Manager;
use Laminas\Service_Manager\Tool\Ahead_Of_Time_Factory_Compiler\Ahead_Of_Time_Factory_Compiler_Interface;
use function mkdir;
use function preg_replace;
use function sprintf;
use function str_replace;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @internal CLI commands are not meant to be used in any upstream projects other than via `laminas-cli`.
 *
 * @psalm-import-type ServiceManagerConfiguration from ServiceManager
 */
final class Ahead_Of_Time_Factory_Creator_Command extends Command
{
    public const NAME = 'servicemanager:generate-aot-factories';
    public function __construct(private readonly array $config, private readonly string $factory_target_path, private readonly Ahead_Of_Time_Factory_Compiler_Interface $factory_compiler)
    {
        parent::__construct(self::NAME);
    }
    protected function configure(): void
    {
        $this->set_description('Creates factories which replace the runtime overhead for `ReflectionBasedAbstractFactory`.');
        $this->add_argument('localConfigFilename', Input_Argument::OPTIONAL, 'Should be a path targeting a filename which will be created so that the config autoloading' . ' will pick it up. Using a `.local.php` suffix should verify that the file is overriding existing' . ' configuration.', 'config/autoload/generated-factories.local.php');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        if ($this->factory_target_path === '' || !is_writable($this->factory_target_path)) {
            $output->writeln(sprintf('<error>Please configure the `%s` configuration key in your projects config and ensure that the' . ' directory is registered to the composer autoloader using `classmap` and writable by the executing' . ' user. In case you are targeting a nonexistent directory, please create the appropriate directory' . ' structure before executing this command.</error>', Config_Provider::CONFIGURATION_KEY_FACTORY_TARGET_PATH));
            return self::FAILURE;
        }
        $local_config_filename = $input->get_argument('localConfigFilename');
        assert(is_string($local_config_filename));
        if (!is_writable(dirname($local_config_filename))) {
            $output->writeln(sprintf('<error>Provided `localConfigFilename` argument "%s" is not writable. In case you are targeting a' . ' nonexistent directory, please create the appropriate directory structure before executing this' . ' command.</error>', $local_config_filename));
            return self::FAILURE;
        }
        $compiled_factories = $this->factory_compiler->compile($this->config);
        if ($compiled_factories === []) {
            $output->writeln('<comment>There is no (more) service registered to use the `ReflectionBasedAbstractFactory`.</comment>');
            return self::SUCCESS;
        }
        $container_configurations = [];
        foreach ($compiled_factories as $factory) {
            $dir_name = preg_replace('/\W/', '', $factory->container_configuration_key);
            assert(is_string($dir_name));
            $target_directory = sprintf('%s/%s', $this->factory_target_path, $dir_name);
            $factory_class_name = sprintf('%sFactory', $factory->fully_qualified_class_name);
            if (class_exists($factory_class_name)) {
                $output->writeln(sprintf('<error>There is already an existing factory class registered for "%s": %s</error>', $factory->fully_qualified_class_name, $factory_class_name));
                return self::FAILURE;
            }
            if (!is_dir($target_directory)) {
                if (!mkdir($target_directory, recursive: true) && !is_dir($target_directory)) {
                    throw new RuntimeException(sprintf('Unable to create directory "%s".', $target_directory));
                }
            }
            $factory_file_name = sprintf('%s/%s.php', $target_directory, str_replace('\\', '_', $factory_class_name));
            file_put_contents($factory_file_name, $factory->generated_factory);
            if (!isset($container_configurations[$factory->container_configuration_key])) {
                $container_configurations[$factory->container_configuration_key] = ['factories' => []];
            }
            // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.NoAssignment
            /**
             * Psalm has to understand that the `factoryClassName` variable contains a class-string to a factory which
             * will be available once persisted to the filesystem and loaded via composer autoloading.
             *
             * Sadly, we do have to do this as psalm is not able to infer concatenated arrays.
             *
             * @var class-string<FactoryInterface> $factoryClassName
             */
            $container_configurations[$factory->container_configuration_key]['factories'] += [$factory->fully_qualified_class_name => $factory_class_name];
        }
        file_put_contents($local_config_filename, $this->create_local_aot_container_config_content($container_configurations));
        $output->writeln(sprintf('<info>Successfully created %d factories.</info>', count($compiled_factories)));
        return self::SUCCESS;
    }
    /**
     * @param non-empty-array<non-empty-string,ServiceManagerConfiguration> $containerConfigurations
     * @return non-empty-string
     */
    private function create_local_aot_container_config_content(array $container_configurations): string
    {
        return sprintf('<?php %s', Var_Exporter::export($container_configurations, Var_Exporter::ADD_RETURN | Var_Exporter::CLOSURE_SNAPSHOT_USES));
    }
}