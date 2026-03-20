<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use RuntimeException as SplRuntimeException;
/**
 * This exception is thrown when the service locator do not manage to create
 * the service (factory that has an error...)
 *
 * @final
 */
class Service_Not_Created_Exception extends Spl_Runtime_Exception implements Exception_Interface
{
}