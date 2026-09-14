<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

/**
 * Autoloader configuration.
 *
 * This class is required before the application autoloader is instantiated.
 */
class Autoload extends AutoloadConfig
{
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    public $classmap = [];

    public $files = [];

    public $helpers = [];
}
