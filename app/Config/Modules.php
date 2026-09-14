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
     */
    public array $discoverIn = [
        APPPATH,
    ];
}
