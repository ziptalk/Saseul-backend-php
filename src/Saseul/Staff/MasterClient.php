<?php

namespace Saseul\Staff;

use IPC\TCPClient;
use IPC\TCPCommand;
use Saseul\Config;

class MasterClient
{
    private static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private $client;

    public function __construct()
    {
        $this->client = new TCPClient();
        $this->client->connect(Config::MASTER_ADDR, Config::MASTER_PORT);
    }

    public function send(string $type, $data = null)
    {
        if (!$this->client->isConnect()) {
            $this->client->connect(Config::MASTER_ADDR, Config::MASTER_PORT);
        }

        return $this->client->send(new TCPCommand($type, $data));
    }
}
