<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Modules extends BaseConfig
{
    /**
     * Whether module discovery is enabled.
     */
    public bool $enabled = true;

    /**
     * Paths where CodeIgniter should discover modules.
     *
     * Keep this empty because the current application does not use
     * Composer-discovered CodeIgniter modules. Using APPPATH here would
     * require the APPPATH constant before Config\Modules is instantiated
     * during Spark bootstrap.
     */
    public array $discoverIn = [];
}
