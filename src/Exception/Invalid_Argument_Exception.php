<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use function get_debug_type;
use InvalidArgumentException as SplInvalidArgumentException;
use Laminas\Service_Manager\Initializer\Initializer_Interface;
use function sprintf;
/**
 * @inheritDoc
 */
class InvalidArgumentException extends Spl_Invalid_Argument_Exception implements Exception_Interface
{
    public static function from_invalid_initializer(mixed $initializer): self
    {
        return new self(sprintf('An invalid initializer was registered. Expected a callable or an' . ' instance of "%s"; received "%s"', Initializer_Interface::class, get_debug_type($initializer)));
    }
}