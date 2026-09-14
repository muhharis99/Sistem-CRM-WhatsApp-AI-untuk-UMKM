<?php

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

/**
 * CodeIgniter module discovery configuration.
 *
 * This class is loaded before the application autoloader is initialized,
 * so it must extend the framework Modules base class rather than BaseConfig.
 */
class Modules extends BaseModules
{
    public $enabled = true;
    public $discoverInComposer = true;
    public $composerPackages = [];
    public $aliases = [
        'events',
        'filters',
        'registrars',
        'routes',
        'services',
    ];
}
