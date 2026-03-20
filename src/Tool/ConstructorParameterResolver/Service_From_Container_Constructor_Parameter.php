<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver;

final class Service_From_Container_Constructor_Parameter
{
    /**
     * @param non-empty-string $serviceName
     */
    public function __construct(public string $service_name)
    {
    }
}