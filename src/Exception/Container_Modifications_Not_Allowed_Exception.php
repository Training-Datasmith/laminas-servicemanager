<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use DomainException;
use function sprintf;
/** @final */
class Container_Modifications_Not_Allowed_Exception extends DomainException implements Exception_Interface
{
    /**
     * @param string $service Name of service that already exists.
     */
    public static function from_existing_service(string $service): self
    {
        return new self(sprintf('The container does not allow replacing or updating a service' . ' with existing instances; the following service' . ' already exists in the container: %s', $service));
    }
}