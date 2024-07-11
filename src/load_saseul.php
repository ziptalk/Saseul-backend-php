<?php

if (!is_file(ROOT. DS. 'saseul.ini')) {
    print_r(PHP_EOL. 'saseul.ini file does not exist. '. PHP_EOL);
    exit;
}

# const;
define('SASEUL_VERSION', '2.1.9.6');
define('SASEUL_INI', parse_ini_file(ROOT. DS. 'saseul.ini'));
define('SCRIPT_BIN', SOURCE. DS. 'saseul-script');
define('SERVICE_BIN', SOURCE. DS. 'saseulsvc');

use Core\Logger;
use Saseul\Config;

# set log file;
Logger::$log_file = SASEUL_INI['log'] ?? DEBUG_LOG;
Logger::$log_file = is_dir(dirname(Logger::$log_file)) ? Logger::$log_file : DEBUG_LOG;

# set variables;
# base;
Config::$_version = SASEUL_INI['version'] ?? SASEUL_VERSION;
Config::$_data = SASEUL_INI['data'] ?? Config::$_data;

# network;
Config::$_peers = SASEUL_INI['peers'] ?? Config::$_peers;
Config::$_network = SASEUL_INI['network_name'] ?? Config::$_network;
Config::$_system_nonce = SASEUL_INI['system_nonce'] ?? Config::$_system_nonce;
Config::$_genesis_address = SASEUL_INI['genesis_address'] ?? Config::$_genesis_address;
Config::$_manager_addresses = SASEUL_INI['manager_addresses'] ?? Config::$_manager_addresses;

# node setting;
Config::$_ledger = SASEUL_INI['ledger'] ?? Config::$_ledger;
Config::$_database = (bool) (SASEUL_INI['database'] ?? Config::$_database);
Config::$_collect = (bool) (SASEUL_INI['collect'] ?? Config::$_collect);
Config::$_consensus = (bool) (SASEUL_INI['consensus'] ?? Config::$_consensus);
Config::$_mining = (bool) (SASEUL_INI['mining'] ?? Config::$_mining);

# data directory setting;
//Config::$_data_dir = SASEUL_INI['data_dir'] ?? Config::$_data_dir;

# database setting;
Config::$_mysql_host = SASEUL_INI['mysql_host'] ?? Config::$_mysql_host;
Config::$_mysql_port = SASEUL_INI['mysql_port'] ?? Config::$_mysql_port;
Config::$_mysql_user = SASEUL_INI['mysql_user'] ?? Config::$_mysql_user;
Config::$_mysql_database = SASEUL_INI['mysql_database'] ?? Config::$_mysql_database;
Config::$_mysql_password = SASEUL_INI['mysql_password'] ?? Config::$_mysql_password;

# set default;
Config::$_data = is_dir(Config::$_data) ? Config::$_data : ROOT. DS. 'data';

# error handler;
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return;
    }

    switch ($errno) {
        case E_USER_ERROR:
            $msg = PHP_EOL.'ERROR: ';
            break;
        case E_USER_WARNING:
            $msg = PHP_EOL.'WARNING: ';
            break;
        case E_USER_NOTICE:
            $msg = PHP_EOL.'NOTICE: ';
            break;
        default:
            $msg = PHP_EOL.'UNKNOWN: ';
            break;
    }

    $e = new \Exception();

    $msg.= '['. date('Y-m-d H:i:s'). '] '. "[{$errno}] {$errstr} in {$errfile} on line {$errline} ".PHP_EOL.PHP_EOL;
    $msg.= $e->getTraceAsString().PHP_EOL;

    $file = SASEUL_INI['err_file'] ? ROOT. DS. SASEUL_INI['err_file'] : DEBUG_LOG;
    $f = @fopen($file, 'a');

    if (is_resource($f)) {
        fwrite($f, $msg);
        fclose($f);
    }
});

# fatal error handler
register_shutdown_function(function () {
    $err = error_get_last();

    $errno = $err["type"] ?? '';
    $errfile = $err["file"] ?? '';
    $errline = $err["line"] ?? '';
    $errstr = $err["message"] ?? '';

    if (empty($errno) || !error_reporting()) {
        return;
    }

    $msg = '['. date('Y-m-d H:i:s'). '] '. "[Fatal Error] {$errstr} in {$errfile} on line {$errline} ".PHP_EOL.PHP_EOL;

    $file = SASEUL_INI['err_file'] ? ROOT. DS. SASEUL_INI['err_file'] : DEBUG_LOG;
    $f = @fopen($file, 'a');

    if (is_resource($f)) {
        fwrite($f, $msg);
        fclose($f);
    }
});
