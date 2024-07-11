<?php

namespace IPC;

use Core\Logger;
use SplQueue;
use Util\Timer;

class TCPClient extends TCPBase
{
    protected $connection;
    protected $timer;
    protected $command_queue;

    protected $listen = true;

    protected $reads_data = '';
    protected $writes_data;

    protected $read = [];
    protected $write = [];

    public function __construct()
    {
        $this->timer = new Timer();
        $this->addListener('response', [ $this, 'response' ]);
    }

    public function __destruct()
    {
        $this->end();
    }

    public function connect(?string $addr = null, ?int $port = null): bool
    {
        if ($this->isConnect()) {
            $this->disconnect();
        }

        $this->addr = $addr ?? $this->addr;
        $this->port = $port ?? $this->port;
        $uri = "tcp://$this->addr:$this->port";

        $connection = @stream_socket_client(
            $uri, $errno, $errstr, 0, STREAM_CLIENT_CONNECT
        );

        if ($connection === false) {
            Logger::log($errstr);
            Logger::log("[TCPClient] Connection to $uri failed: $errno");
            return false;
        }

        stream_set_blocking($connection, false);
        $this->connection = $connection;

        return true;
    }

    public function disconnect() {
        if (!is_resource($this->connection)) {
            return;
        }

        stream_socket_shutdown($this->connection, STREAM_SHUT_RDWR);
        fclose($this->connection);
        $this->connection = null;
    }

    public function isConnect(): bool
    {
        if (is_resource($this->connection)) {
            return true;
        }

        return false;
    }

    public function addWriteData(string $data): void
    {
        $parts = str_split($data, 4096);

        foreach ($parts as $part) {
            $this->writes_data->enqueue($part);
        }
    }

    public function removeWriteData(): void
    {
        $this->writes_data = null;
    }

    public function addReadData(string $data): void
    {
        $this->reads_data.= $data;
    }

    public function removeReadData()
    {
        $this->reads_data = '';
    }

    public function init()
    {
        $this->listen = true;
        $this->command_queue = new SplQueue();

        $this->reads_data = '';
        $this->writes_data = new SplQueue();
    }

    public function end(bool $disconnect = true)
    {
        $this->listen = false;
        $this->available = false;

        $this->read = [];
        $this->write = [];

        $this->removeReadData();
        $this->removeWriteData();

        if ($disconnect) {
            $this->disconnect();
        }
    }

    public function send(TCPCommand $command, int $timeout = 2000000)
    {
        $this->init();
        $response = null;

        $this->timer->start();
        $this->addWriteData($this->encode($command));

        while ($this->listen && $this->timer->lastInterval() < $timeout) {
            $this->selectOperation();
            $this->readOperation();

            while ($received_command = $this->popCommand()) {
                $response = $this->run($received_command);
                $this->end(false);
            }

            $this->writeOperation();
        }

        if ($this->listen) {
            $this->end(false);
        }

        return $response;
    }

    public function response(TCPCommand $command)
    {
        return $command->data();
    }

    public function addCommand(TCPCommand $command)
    {
        if (is_null($this->command_queue)) {
            $this->command_queue = new SplQueue();
        }

        $this->command_queue->enqueue($command);
    }

    public function popCommand(): ?TCPCommand
    {
        if ($this->command_queue->isEmpty()) {
            return null;
        }

        return $this->command_queue->dequeue();
    }

    public function selectOperation()
    {
        $this->available = false;

        if (!is_resource($this->connection)) {
            return;
        }

        $conn = [$this->connection];

        $this->read = $conn;
        $this->write = $conn;

        $except = [];

        if (count($this->read) > 0 || count($this->write) > 0) {
            $this->available = stream_select($this->read, $this->write,
                $except, 0, 0);
        }
    }

    public function writeOperation()
    {
        if (!$this->available) {
            return;
        }

        foreach ($this->write as $connection) {
            $data = $this->writes_data;

            if (is_null($data) || $data->isEmpty()) {
                return;
            }

            if (!is_resource($connection)) {
                Logger::log("[TCPClient] There is no connection. ");
                $this->end();
                return;
            }

            $sent = fwrite($connection, $data->dequeue(), 4096);
            $this->timer->check();

            if ($sent === 0) {
                Logger::log("[TCPClient] Unable to write data. ");
                $this->end();
                return;
            }
        }
    }

    public function readOperation()
    {
        if (!$this->available) {
            return;
        }

        foreach ($this->read as $connection) {
            $read = fread($connection, 4096);

            if (strlen($read) === 0 && feof($connection)) {
                Logger::log("[TCPClient] Unable to read.");
                $this->end();
                break;
            }

            $this->timer->check();

            $this->addReadData($read);
            $data_length = strlen($this->reads_data);
            $command_length = $this->getLength($this->reads_data, $data_length);

            if ($command_length > self::READ_MAXLENGTH) {
                Logger::log("[TCPClient] Invalid command.");
                $this->end();
                return;
            }

            if (is_null($command_length) || $data_length < $command_length) {
                # insufficient;
                return;
            }

            $command = $this->decode($this->reads_data);
            $command->sender($connection);

            $this->reads_data = substr($this->reads_data, $command_length);
            $this->addCommand($command);
            $this->end();
        }
    }
}
