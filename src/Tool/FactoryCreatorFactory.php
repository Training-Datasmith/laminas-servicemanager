<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool;

use function assert;
use Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver\Constructor_Parameter_Resolver_Interface;
use Psr\Container\Container_Interface;
/**
 * @internal
 */
final class Factory_Creator_Factory
{
    public function __invoke(Container_Interface $container): Factory_Creator_Interface
    {
        $resolver = $container->get(Constructor_Parameter_Resolver_Interface::class);
        assert($resolver instanceof Constructor_Parameter_Resolver_Interface);
        return new Factory_Creator($container, $resolver);
    }
}