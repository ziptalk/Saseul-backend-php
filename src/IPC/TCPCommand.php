<?php

namespace IPC;

use Util\Hasher;

class TCPCommand
{
    public const ID_LENGTH_LIMIT = 256;
    public const TYPE_LENGTH_LIMIT = 256;

    private $id;
    private $type;
    private $data;
    private $sender;

    public function __construct(string $type = null, $data = null) {
        $this->id = $this->generateId();
        $this->type = $type;
        $this->data = $data;
    }

    private function generateId(): string
    {
        return Hasher::hextime(). bin2hex(random_bytes(32 - Hasher::HEX_TIME_BYTES));
    }

    public function isValidId($id): bool
    {
        return (is_string($id) && strlen($id) < self::ID_LENGTH_LIMIT);
    }

    public function isValidType($type): bool
    {
        return (is_string($type) && strlen($type) < self::TYPE_LENGTH_LIMIT);
    }

    public function isValidSender($sender): bool
    {
        return is_resource($sender);
    }

    public function id($id = null) {
        $this->id = ($this->isValidId($id) ? $id : null) ?? ($this->id ?? $this->generateId());

        return $this->id;
    }

    public function type($type = null) {
        $this->type = ($this->isValidType($type) ? $type : null) ?? $this->type;

        return $this->type;
    }

    public function data($data = null) {
        $this->data = $data ?? $this->data;

        return $this->data;
    }

    public function sender($sender = null)
    {
        if (!is_null($sender) && $this->isValidSender($sender)) {
            $this->sender = $sender ?? $this->sender;
        }

        return $this->sender;
    }
}
