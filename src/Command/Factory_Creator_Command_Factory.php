<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Command;

use function assert;
use Laminas\Service_Manager\Tool\Factory_Creator_Interface;
use Psr\Container\Container_Interface;
/**
 * @internal Factories are not meant to be used in any upstream projects.
 */
final class Factory_Creator_Command_Factory
{
    public function __invoke(Container_Interface $container): Factory_Creator_Command
    {
        $creator = $container->get(Factory_Creator_Interface::class);
        assert($creator instanceof Factory_Creator_Interface);
        return new Factory_Creator_Command($creator);
    }
}