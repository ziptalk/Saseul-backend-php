<?php

namespace IPC;

use Core\Logger;

class UDPSocket extends UDPBase
{
    public $listeners = [];

    public function create(?string $addr = null, ?int $port = null): void
    {
        $this->master = socket_create(AF_INET, SOCK_DGRAM, 0);
        $this->addr = $addr ?? $this->addr;
        $this->port = $port ?? $this->port;

        if (!$this->master) {
            Logger::log('UDPSocket create failed. ');
        }
    }

    public function bind(): void
    {
        $bind = socket_bind($this->master, $this->addr, $this->port);

        if (!$bind) {
            Logger::log('UDPSocket bind failed. ');
        }
    }

    public function addListener(string $key, callable $func): void
    {
        $this->listeners[$key] = $func;
    }

    public function listen()
    {
        # operate;
        if (@socket_recvfrom($this->master, $buffer, $this->mtu, 0, $this->addr, $this->port)) {
            $type = $buffer[0];
            $data = unserialize(substr($buffer, 1));
            $resp = $type. serialize($this->run($type, $data));

            @socket_sendto($this->master, $resp, strlen($resp), 0, $this->addr, $this->port);
        }
    }

    public function run(string $command, $data)
    {
        $func = $this->listeners[$command] ?? null;

        if (!is_null($func)) {
            return call_user_func($func, $data);
        }

        return null;
    }
}