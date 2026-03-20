<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use RuntimeException as SplRuntimeException;
/**
 * This exception is thrown by plugin managers when the created object does not match
 * the plugin manager's conditions
 *
 * @final
 */
class Invalid_Service_Exception extends Spl_Runtime_Exception implements Exception_Interface
{
}