<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use Psr\Container\Container_Exception_Interface;
/**
 * Base exception for all Laminas\ServiceManager exceptions.
 */
interface Exception_Interface extends Container_Exception_Interface
{
}