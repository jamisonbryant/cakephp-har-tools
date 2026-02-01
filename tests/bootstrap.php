<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

// Ensure core defaults exist for CakePHP HTTP classes in tests.
Cake\Core\Configure::write('App.encoding', 'UTF-8');
