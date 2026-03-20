<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use InvalidArgumentException as SplInvalidArgumentException;
use Psr\Container\Not_Found_Exception_Interface;
/**
 * This exception is thrown when the service locator do not manage to find a
 * valid factory to create a service
 *
 * @final
 */
class Service_Not_Found_Exception extends Spl_Invalid_Argument_Exception implements Exception_Interface, Not_Found_Exception_Interface
{
}