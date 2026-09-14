<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Application module discovery configuration.
 *
 * The current application does not register Composer-discovered modules,
 * so discovery is intentionally disabled. Keeping this config as a normal
 * BaseConfig also avoids invoking framework services during early Spark boot.
 */
class Modules extends BaseConfig
{
    public bool $enabled = false;
    public bool $discoverInComposer = false;
    public array $composerPackages = [];
    public array $aliases = [
        'events',
        'filters',
        'registrars',
        'routes',
        'services',
    ];
}
