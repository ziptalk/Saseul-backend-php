<?php

namespace IPC;

use Core\Logger;
use SplQueue;

class TCPSocket extends TCPBase
{
    protected $master;
    protected $command_queue;

    protected $read = [];
    protected $write = [];

    protected $read_streams = [];
    protected $reads_data = [];
    protected $reads_length = [];

    protected $write_streams = [];
    protected $writes_data = [];

    public function __construct() {
        $this->command_queue = new SplQueue();
        $this->addListener('isConnect', [ $this, 'isConnect' ]);
    }

    public function __destruct()
    {
        $this->turnOff();
    }

    public function isConnect(TCPCommand $command) {
        $this->sendResponse(true, $command);
    }

    public function isListening(): bool
    {
        return is_resource($this->master);
    }

    public function listen(?string $addr = null, ?int $port = null): bool
    {
        $this->addr = $addr ?? $this->addr;
        $this->port = $port ?? $this->port;
        $uri = "tcp://$this->addr:$this->port";

        if (is_resource($this->master)) {
            Logger::log("Failed to listen on $uri: It's already listening. ");
            return false;
        }

        $context = stream_context_create([
            'socket' => [
                'backlog' => 511,
                'so_reuseport' => 1
            ]
        ]);

        $socket = stream_socket_server(
            $uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context
        );

        if ($socket === false) {
            Logger::log("Failed to listen on $uri: $errstr ");
            return false;
        }

        stream_set_blocking($socket, false);

        $this->addReadStream($socket);
        $this->master = $socket;

        Logger::log("[{$this->streamName($socket)}] listen ");
        return true;
    }

    public function turnOff(): void
    {
        if (!is_resource($this->master)) {
            return;
        }

        $this->removeReadStream($this->master);
        stream_socket_shutdown($this->master, STREAM_SHUT_RDWR);
        fclose($this->master);

        $this->available = false;
        $this->master = null;
    }

    public function popReceivedCommandQueue(): ?TCPCommand
    {
        if ($this->command_queue->isEmpty()) {
            return null;
        }

        return $this->command_queue->dequeue();
    }

    public function addReceivedCommandQueue(TCPCommand $command): void {
        $this->command_queue->enqueue($command);
    }

    public function send(?string $string, $connection): void
    {
        if (!is_resource($connection)) {
            return;
        }

        $this->addWriteStream($connection, $string);
    }

    public function sendResponse($msg, TCPCommand $command): void
    {
        if (!is_resource($this->master)) {
            return;
        }

        $response = new TCPCommand('response', $msg);
        $response->id($command->id());
        $this->send($this->encode($response), $command->sender());
    }

    public function selectOperation(): void
    {
        $this->available = false;
        $this->read = $this->read_streams;
        $this->write = $this->write_streams;

        $except = [];

        if (count($this->read) > 0 || count($this->write) > 0) {
            $this->available = stream_select($this->read, $this->write,
                $except, 0, 0);
        }
    }

    public function isWorking(): bool
    {
        return $this->available;
    }

    protected function streamName($stream)
    {
        if (!is_resource($stream)) {
            return (int) $stream;
        }

        if ((int) $this->master === (int) $stream) {
            return stream_socket_get_name($stream, false);
        }

        return stream_socket_get_name($stream, true);
    }

    public function readOperation(): void
    {
        if (!$this->available) {
            return;
        }

        foreach ($this->read as $stream) {
            # socket;
            if ((int)$stream === (int)$this->master) {
                $this->createConnection($this->master);
                return;
            }

            # connection;
            $key = (int)$stream;

            # read buffer
            if ($this->reads_length[$key] > 0) {
                $read = fread($stream, min(4096, $this->reads_length[$key]));
            } else {
                $read = fread($stream,4096);
            }

            # length counting
            if ($this->reads_length[$key] === 0) {
                if (strlen($read) === 4096) {
                    $this->reads_length[$key] = $this->getLength($read, 4096);
                }
            }

            if (strlen($read) > 0) {
                # buffer counting
                $this->reads_length[$key] = $this->reads_length[$key] - strlen($read);
                $this->reads_data[$key] .= $read;

                # command;
                $command = $this->unwrapCommand($stream);

                if (!is_null($command)) {
                    $this->addReceivedCommandQueue($command);
                    return;
                }

            } elseif(feof($stream)) {
                # disconnected;
                $this->removeReadStream($stream);
                $this->destroyConnection($stream);
                return;
            }
        }
    }

    public function writeOperation(): void
    {
        if (!$this->available) {
            return;
        }

        foreach ($this->write as $stream) {
            $key = (int)$stream;
            $data = $this->writes_data[$key] ?? null;

            if (is_null($data) || $data->isEmpty()) {
                return;
            }

            if (!is_resource($stream)) {
                Logger::log("[{$this->streamName($stream)}] Stream is not open. ");
                $this->destroyConnection($stream);
                return;
            }

            $sent = fwrite($stream, $data->dequeue(), 4096);

            if ($sent === 0) {
                Logger::log("[{$this->streamName($stream)}] Unable to write data. ");
                $this->destroyConnection($stream);
                return;
            }
        }
    }

    protected function createConnection($socket) {
        if ($connection = stream_socket_accept($socket, 0)) {
            $this->addReadStream($connection);
        }
    }

    protected function destroyConnection($connection) {
        $this->removeReadStream($connection);
        $this->removeWriteStream($connection);

        if (!is_resource($connection)) {
            return;
        }

        stream_socket_shutdown($connection, STREAM_SHUT_RDWR);
        fclose($connection);
    }

    protected function addReadStream($stream)
    {
        $key = (int)$stream;

        if (!isset($this->read_streams[$key])) {
            $this->read_streams[$key] = $stream;
            $this->reads_data[$key] = '';
            $this->reads_length[$key] = 0;
        }
    }

    protected function addWriteStream($stream, string $data)
    {
        $key = (int)$stream;

        if (!isset($this->write_streams[$key])) {
            $this->write_streams[$key] = $stream;
            $this->writes_data[$key] = new SplQueue();
        }

        $parts = str_split($data, 4096);

        foreach ($parts as $part) {
            $this->writes_data[$key]->enqueue($part);
        }
    }

    protected function removeReadStream($stream)
    {
        $key = (int)$stream;

        unset(
            $this->read_streams[$key],
            $this->reads_data[$key],
            $this->reads_length[$key]
        );
    }

    protected function removeWriteStream($stream) {
        $key = (int)$stream;

        unset(
            $this->write_streams[$key],
            $this->writes_data[$key]
        );
    }

    protected function unwrapCommand($stream): ?TCPCommand
    {
        $key = (int)$stream;
        $data = $this->reads_data[$key] ?? '';

        $data_length = strlen($data);
        $command_length = $this->getLength($data, $data_length);

        if ($command_length > self::READ_MAXLENGTH) {
            Logger::log("[{$this->streamName($stream)}] Invalid command.");
            $this->destroyConnection($stream);
            return null;
        }

        if (is_null($command_length) || $data_length < $command_length) {
            # insufficient;
            return null;
        }

        $command = $this->decode($data);
        $this->reads_data[$key] = substr($this->reads_data[$key], $command_length);
        $command->sender($stream);

        return $command;
    }
}
