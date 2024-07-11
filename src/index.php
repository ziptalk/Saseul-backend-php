<?php

# autoload;
require_once("load_core.php");
require_once("load_saseul.php");

use Core\Api;
use Core\Loader;
use Core\Result;
use Saseul\Data\Env;

# set ini;
ini_set('memory_limit', '128M');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Access-Control-Allow-Methods: GET, POST');

if (!Env::check()) {
    $api = new Api();
    $api->fail(Result::INTERNAL_SERVER_ERROR, 'SASEUL Services has not been initialized. ');
    exit;
}

Env::load();
Loader::api();
