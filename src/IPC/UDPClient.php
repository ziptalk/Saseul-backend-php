<?php

namespace IPC;

use Core\Logger;

class UDPClient extends UDPBase
{
    public function create(?string $addr = null, ?int $port = null): void
    {
        $this->master = socket_create(AF_INET, SOCK_DGRAM, 0);
        $this->addr = $addr ?? $this->addr;
        $this->port = $port ?? $this->port;

        socket_set_option($this->master, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => $this->timeout]);
    }

    public function reconnect(): void
    {
        if (is_resource($this->master)) {
            Logger::log('re-connect udp client.. ');
            @socket_close($this->master);
            $this->create($this->addr, $this->port);
            usleep($this->timeout);
        }
    }

    public function send(string $command, string $serialized, int $retry = 1, int $usleep = 100): ?string
    {
        if (@socket_sendto($this->master, $serialized, strlen($serialized), 0, $this->addr, $this->port)) {
            for ($i = 0; $i < $retry; $i++) {
                if (@socket_recvfrom($this->master, $buffer, $this->mtu, 0, $this->addr, $this->port)) {
                    $_type = $buffer[0];

                    if ($command === $_type) {
                        return substr($buffer, 1);
                    }
                }
                usleep($usleep);
            }
        }

        return null;
    }

    public function once(string $command, $data = null)
    {
        $serialized = $command. serialize($data);
        $result = $this->send($command, $serialized, 3);

        if (is_null($result)) {
            return null;
        }

        return unserialize($result);
    }

    public function rewind(string $command, $data = null)
    {
        $wait = 0;
        $serialized = $command. serialize($data);

        do {
            if ($wait > 0) {
                $this->reconnect();
            }

            usleep($wait);
            $result = $this->send($command, $serialized, 20);
            $wait = $this->timeout * 2;
        } while (is_null($result));

        return unserialize($result);
    }
}