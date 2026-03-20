<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Tool\Constructor_Parameter_Resolver;

final class Fallback_Constructor_Parameter
{
    public function __construct(public mixed $argument_value)
    {
    }
}