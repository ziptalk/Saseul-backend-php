<?php

namespace Saseul\DataSource;

use Core\Logger;
use Saseul\Config;
use Util\MySQL;

class Database extends MySQL
{
    public static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function init() {
        $this->host = Config::$_mysql_host;
        $this->port = Config::$_mysql_port;
        $this->user = Config::$_mysql_user;
        $this->password = Config::$_mysql_password;
        $this->name = Config::$_mysql_database;
    }

    public function error(string $msg)
    {
        Logger::log($msg);
    }

    public function fail()
    {
        Config::$_database = false;
    }

    public function databaseName(): string
    {
        return $this->name;
    }
}