<?php

namespace IPC;

use Util\Parser;

class TCPBase
{
    protected const READ_MAXLENGTH = 67108864;
    protected const ID_BYTES_SIZE = 1;
    protected const TYPE_BYTES_SIZE = 1;
    protected const DATA_BYTES_SIZE = 4;
    protected const PREFIX_SIZE = self::ID_BYTES_SIZE + self::TYPE_BYTES_SIZE + self::DATA_BYTES_SIZE;

    # default: tcp://127.0.0.1:9933
    protected $addr = '127.0.0.1';
    protected $port = 9933;

    protected $listeners = [];
    protected $available = false;

    public function addListener(string $type, callable $func): void
    {
        $this->listeners[$type] = $func;
    }

    public function run(TCPCommand $command)
    {
        if (is_null($command->type())) {
            return null;
        }

        $func = $this->listeners[$command->type()] ?? null;

        if (!is_callable($func)) {
            return null;
        }

        return call_user_func($func, $command);
    }

    public function getLength(string $encoded_command, int $data_length): ?int
    {
        if ($data_length < 6) {
            return null;
        }

        $id_bytes = Parser::bindec(substr($encoded_command, 0, self::ID_BYTES_SIZE));
        $type_bytes = Parser::bindec(substr($encoded_command, self::ID_BYTES_SIZE, self::TYPE_BYTES_SIZE));
        $data_bytes = Parser::bindec(
            substr($encoded_command, self::ID_BYTES_SIZE + self::TYPE_BYTES_SIZE, self::DATA_BYTES_SIZE)
        );

        return (self::PREFIX_SIZE + $id_bytes + $type_bytes + $data_bytes);
    }

    public function encode(TCPCommand $command): string
    {
        $data = $command->data();
        $data = serialize($data);

        $id_bytes = Parser::decbin(strlen($command->id()), self::ID_BYTES_SIZE);
        $type_bytes = Parser::decbin(strlen($command->type()), self::TYPE_BYTES_SIZE);
        $data_bytes = Parser::decbin(strlen($data), self::DATA_BYTES_SIZE);

        return ($id_bytes. $type_bytes. $data_bytes. $command->id(). $command->type(). $data);
    }

    public function decode(string $encoded_command): ?TCPCommand
    {
        $id_bytes = Parser::bindec(substr($encoded_command, 0, self::ID_BYTES_SIZE));
        $type_bytes = Parser::bindec(substr($encoded_command, self::ID_BYTES_SIZE, self::TYPE_BYTES_SIZE));
        $data_bytes = Parser::bindec(
            substr($encoded_command, self::ID_BYTES_SIZE + self::TYPE_BYTES_SIZE, self::DATA_BYTES_SIZE)
        );

        $id = substr($encoded_command, self::PREFIX_SIZE, $id_bytes);
        $type = substr($encoded_command, self::PREFIX_SIZE + $id_bytes, $type_bytes);
        $data = substr($encoded_command, self::PREFIX_SIZE + $id_bytes + $type_bytes, $data_bytes);
        $data = unserialize($data);

        $command = new TCPCommand();
        $command->id($id);
        $command->type($type);
        $command->data($data);

        return $command;
    }
}
