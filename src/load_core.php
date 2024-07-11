<?php

# const;
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));
define('SOURCE', __DIR__);
define('LOADER', ROOT. DS. 'loader.json');
define('DEBUG_LOG', ROOT. DS. 'debug.log');

# autoloader;
spl_autoload_register(function ($class_name) {
    if (class_exists($class_name, false)) {
        return;
    }

    $filename = SOURCE. DS. str_replace('\\', DS, $class_name). '.php';

    if (file_exists($filename)) {
        require_once($filename);
    }
});