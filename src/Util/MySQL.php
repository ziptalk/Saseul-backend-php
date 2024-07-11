<?php

namespace Util;

class MySQL
{
    protected $host;
    protected $port;
    protected $user;
    protected $password;
    protected $name;

    protected $connect;

    public function __construct()
    {
        $this->init();
        $this->connect();
    }

    public function __destruct()
    {
        if ($this->isConnect()) {
            mysqli_close($this->connect);
        }
    }

    public function init() {
        // inherit
        $this->host = 'localhost';
        $this->port = 3306;
        $this->user = '';
        $this->password = '';
        $this->name = '';
    }

    public function error(string $msg) {
        // inherit
    }

    public function fail() {
        // inherit
    }

    private function connect()
    {
        try {
            $this->connect = mysqli_connect($this->host, $this->user, $this->password, $this->name);
            $this->connect->set_charset("utf8mb4");
        } catch (\Exception $e) {
//            $msg = 'Error '. $e->getCode(). ': '. $e->getMessage(). PHP_EOL;
//            $msg.= $e->getTraceAsString();

            $this->fail();
            $this->connect = null;
        }
    }

    public function isConnect(): bool
    {
        if ($this->connect === null || @mysqli_ping($this->connect) === false) {
            return false;
        }

        return true;
    }

    private function reconnect()
    {
        if (!$this->isConnect()) {
            $this->connect();
        }
    }

    public function exec($sql) {
        $this->reconnect();

        try {
            if ($rs = mysqli_query($this->connect, $sql)) {
                return $rs;
            }

            return null;
        } catch (\Exception $e) {
            $msg = 'Error '. $e->getCode(). ': '. $e->getMessage(). PHP_EOL;
            $msg.= $e->getTraceAsString();

            $this->error($msg);

            return null;
        }
    }

    public function insertID() {
        $this->reconnect();
        return mysqli_insert_id($this->connect);
    }

    public function escape($string): string
    {
        $this->reconnect();
        return mysqli_real_escape_string($this->connect, $string);
    }
}