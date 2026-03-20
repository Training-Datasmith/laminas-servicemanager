<?php

declare(strict_types=1);

namespace LaminasTest\ServiceManager\Command;

use function file_get_contents;

use Laminas\ServiceManager\Command\ConfigDumperCommand;
use Laminas\ServiceManager\Exception\InvalidArgumentException;
use Laminas\ServiceManager\Tool\ConfigDumperInterface;
use LaminasTest\ServiceManager\TestAsset\ObjectWithScalarDependency;
use LaminasTest\ServiceManager\TestAsset\SimpleDependencyObject;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function sprintf;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[CoversClass(ConfigDumperCommand::class)]
final class ConfigDumperCommandTest extends TestCase
{
    private vfsStreamDirectory $configDir;

    private ConfigDumperCommand $command;

    /** @var MockObject&InputInterface */
    private InputInterface $input;

    /** @var MockObject&OutputInterface */
    private OutputInterface $output;

    /** @var ConfigDumperInterface&MockObject */
    private ConfigDumperInterface $configDumper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configDir    = vfsStream::setup('project');
        $this->configDumper = $this->createMock(ConfigDumperInterface::class);
        $this->input        = $this->createMock(InputInterface::class);
        $this->output       = $this->createMock(OutputInterface::class);

        $this->command = new ConfigDumperCommand($this->configDumper);
    }

    public function assertErrorRaised(string $message): void
    {
        $this->output
            ->expects(self::once())
            ->method('writeln')
            ->with(self::stringContains(sprintf('<error>%s', $message)));
    }

    public function ignoreUnresolvedArguments(): array
    {
        return [
            'short' => ['-i'],
            'long'  => ['--ignore-unresolved'],
        ];
    }

    public function testRaisesExceptionIfConfigFileNotFoundAndDirectoryNotWritable(): void
    {
        vfsStream::newDirectory('config', 0550)
            ->at($this->configDir);
        $config = vfsStream::url('project/config/test.config.php');

        $this->assertErrorRaised(sprintf('Cannot create configuration at path "%s"; not writable.', $config));
        $this->input
            ->method('getArgument')
            ->willReturnMap([
                ['class', 'Not\A\Real\Class'],
                ['configFile', $config],
            ]);
        self::assertEquals(1, $this->command->run($this->input, $this->output));
    }

    public function testGeneratesConfigFileWhenProvidedConfigurationFileNotFound(): void
    {
        vfsStream::newDirectory('config', 0775)
            ->at($this->configDir);
        $config = vfsStream::url('project/config/test.config.php');

        $this->output
            ->expects(self::once())
            ->method('writeln')
            ->with('<info>[DONE]</info> Changes written to ' . $config);

        $this->input
            ->method('getArgument')
            ->willReturnMap([
                ['class', SimpleDependencyObject::class],
                ['configFile', $config],
            ]);

        $this->configDumper
            ->expects(self::once())
            ->method('createDependencyConfig')
            ->with([], SimpleDependencyObject::class, false)
            ->willReturn(['config' => 'value']);

        $this->configDumper
            ->expects(self::once())
            ->method('dumpConfigFile')
            ->with(['config' => 'value'])
            ->willReturn('yada yada');

        self::assertEquals(0, $this->command->run($this->input, $this->output));
        self::assertSame('yada yada', file_get_contents($config));
    }

    public function testGeneratesConfigFileIgnoringUnresolved(): void
    {
        vfsStream::newDirectory('config', 0775)
            ->at($this->configDir);
        $config = vfsStream::url('project/config/test.config.php');

        $this->output
            ->expects(self::once())
            ->method('writeln')
            ->with('<info>[DONE]</info> Changes written to ' . $config);

        $this->input
            ->method('getArgument')
            ->willReturnMap([
                ['class', SimpleDependencyObject::class],
                ['configFile', $config],
            ]);

        $this->input
            ->expects(self::once())
            ->method('hasOption')
            ->with('ignore-unresolved')
            ->willReturn(true);

        $this->configDumper
            ->expects(self::once())
            ->method('createDependencyConfig')
            ->with([], SimpleDependencyObject::class, true)
            ->willReturn(['config' => 'value']);

        $this->configDumper
            ->expects(self::once())
            ->method('dumpConfigFile')
            ->with(['config' => 'value'])
            ->willReturn('yada yada');

        self::assertEquals(0, $this->command->run($this->input, $this->output));
        self::assertSame('yada yada', file_get_contents($config));
    }

    public function testEmitsErrorWhenConfigurationFileDoesNotReturnArray(): void
    {
        $content = file_get_contents(__DIR__ . '/../TestAsset/config/invalid.config.php');
        self::assertIsString($content);

        vfsStream::newFile('config/invalid.config.php')
            ->at($this->configDir)
            ->setContent($content);
        $config = vfsStream::url('project/config/invalid.config.php');

        $this->configDumper
            ->expects(self::never())
            ->method('createDependencyConfig');

        $this->input
            ->method('getArgument')
            ->willReturnMap([
                ['class', SimpleDependencyObject::class],
                ['configFile', $config],
            ]);

        $this->assertErrorRaised('Configuration at path "' . $config . '" does not return an array.');
        self::assertEquals(1, $this->command->run($this->input, $this->output));
    }

    public function testEmitsErrorWhenClassDoesNotExist(): void
    {
        $content = file_get_contents(__DIR__ . '/../TestAsset/config/test.config.php');
        self::assertIsString($content);

        vfsStream::newFile('config/test.config.php')
            ->at($this->configDir)
            ->setContent($content);
        $config = vfsStream::url('project/config/test.config.php');

        $this->input
            ->method('getArgument')
            ->willReturnMap([
                ['class', 'Not\A\Real\Class'],
                ['configFile', $config],
            ]);

        $this->assertErrorRaised('Class "Not\\A\\Real\\Class" does not exist or could not be autoloaded.');
        self::assertEquals(1, $this->command->run($this->input, $this->output));
    }

    public function testEmitsErrorWhenUnableToCreateConfiguration(): void
    {
        $content = file_get_contents(__DIR__ . '/../TestAsset/config/test.config.php');
        self::assertIsString($content);

        vfsStream::newFile('config/test.config.php')
            ->at($this->configDir)
            ->setContent($content);
        $config = vfsStream::url('project/config/test.config.php');

        $this->input
            ->method('getArgument')
            ->willReturnMap([
                ['class', ObjectWithScalarDependency::class],
                ['configFile', $config],
            ]);

        $this->configDumper
            ->expects(self::once())
            ->method('createDependencyConfig')
            ->with([], ObjectWithScalarDependency::class)
            ->willThrowException(new InvalidArgumentException('Whatever'));

        $this->assertErrorRaised('Unable to create config for "' . ObjectWithScalarDependency::class . '":');
        self::assertEquals(1, $this->command->run($this->input, $this->output));
    }
}
