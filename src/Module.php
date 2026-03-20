<?php

declare (strict_types=1);
namespace Laminas\Service_Manager;

final class Module
{
    public function get_config(): array
    {
        $provider = new Config_Provider();
        $config = $provider();
        $config['service_manager'] = $config['dependencies'];
        unset($config['dependencies']);
        return $config;
    }
}