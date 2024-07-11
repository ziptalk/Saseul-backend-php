<?php

namespace IPC;

class UDPBase
{
    protected $master;

    protected $mtu = 32000;
    protected $timeout = 100000;

    # default: udp://127.0.0.1:9934
    protected $addr = '127.0.0.1';
    protected $port = 9934;

    public function __destruct()
    {
        if (is_resource($this->master)) {
            @socket_close($this->master);
        }
    }

    public function mtu(?int $mtu = null): int
    {
        return $this->mtu = $mtu ?? $this->mtu;
    }
}