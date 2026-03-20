<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool;

use function array_map;
use function array_shift;
use function assert;
use Brick\Var_Exporter\Var_Exporter;
use function class_exists;
use function count;
use function implode;
use function is_string;
use Laminas\Service_Manager\Factory\Factory_Interface;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Constructor_Parameter_Resolver_Interface;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Service_From_Container_Constructor_Parameter;
use const PHP_EOL;
use function preg_replace;
use Psr\Container\Container_Interface;
use function sort;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strrpos;
use function substr;
/**
 * @internal
 */
final readonly class Factory_Creator implements Factory_Creator_Interface
{
    private const NAMESPACE_SEPARATOR = '\\';
    // phpcs:disable Generic.Files.LineLength
    private const FACTORY_TEMPLATE = <<<'EOT'
    <?php
    
    declare(strict_types=1);
    %s
    %s
    
    class %sFactory implements FactoryInterface
    {
        public function __invoke(ContainerInterface $container, string $requestedName, array|null $options = null): %s
        {
            return new %s(%s);
        }
    }
    
    EOT;
    // phpcs:enable Generic.Files.LineLength
    private const IMPORT_ALWAYS = [Factory_Interface::class, Container_Interface::class];
    public function __construct(private Container_Interface $container, private Constructor_Parameter_Resolver_Interface $constructor_parameter_resolver)
    {
    }
    public function create_factory(string $class_name, array $aliases = []): string
    {
        $class = $this->get_class_name($class_name);
        $namespace = $this->get_namespace($class_name, $class);
        return sprintf(self::FACTORY_TEMPLATE, $namespace, $this->create_import_statements(), $class, $class, $class, $this->create_argument_string($class_name, $aliases));
    }
    /**
     * @param class-string $className
     * @return non-empty-string
     */
    private function get_class_name(string $class_name): string
    {
        $last_namespace_separator = strrpos($class_name, self::NAMESPACE_SEPARATOR);
        if ($last_namespace_separator === false) {
            return $class_name;
        }
        $class_name = substr($class_name, $last_namespace_separator + 1);
        assert($class_name !== '');
        return $class_name;
    }
    /**
     * @param class-string $className
     * @param array<string,string> $aliases
     * @return array<string>
     */
    private function get_constructor_parameters(string $class_name, array $aliases): array
    {
        $dependencies = $this->constructor_parameter_resolver->resolve_constructor_parameter_service_names_or_fallback_types($class_name, $this->container, $aliases);
        $stringified_constructor_arguments = [];
        foreach ($dependencies as $dependency) {
            if ($dependency instanceof Service_From_Container_Constructor_Parameter) {
                $stringified_constructor_arguments[] = sprintf('$container->get(%s)', $this->export($dependency->service_name));
                continue;
            }
            $stringified_constructor_arguments[] = $this->export($dependency->argument_value);
        }
        return $stringified_constructor_arguments;
    }
    /**
     * @param class-string $className
     * @param array<string,string> $aliases
     */
    private function create_argument_string(string $class_name, array $aliases): string
    {
        $arguments = array_map(static fn(string $dependency): string => sprintf('%s', $dependency), $this->get_constructor_parameters($class_name, $aliases));
        switch (count($arguments)) {
            case 0:
                return '';
            case 1:
                return array_shift($arguments);
            default:
                $argument_pad = str_repeat(' ', 12);
                $close_pad = str_repeat(' ', 8);
                return sprintf("\n%s%s\n%s", $argument_pad, implode(",\n" . $argument_pad, $arguments), $close_pad);
        }
    }
    private function create_import_statements(): string
    {
        $imports = self::IMPORT_ALWAYS;
        sort($imports);
        return implode("\n", array_map(static fn(string $import): string => sprintf('use %s;', $import), $imports));
    }
    private function export(mixed $value): string
    {
        if (is_string($value) && class_exists($value)) {
            return sprintf('\%s::class', $value);
        }
        return Var_Exporter::export($value, Var_Exporter::NO_CLOSURES | Var_Exporter::NO_SERIALIZE | Var_Exporter::NO_SERIALIZE | Var_Exporter::NO_SET_STATE);
    }
    /**
     * @param class-string $className
     * @param non-empty-string $class
     */
    private function get_namespace(string $class_name, string $class): string
    {
        if (!str_contains($class_name, self::NAMESPACE_SEPARATOR)) {
            return '';
        }
        return sprintf('%snamespace %s;%s', PHP_EOL, preg_replace('/\\\\' . $class . '$/', '', $class_name), PHP_EOL);
    }
}