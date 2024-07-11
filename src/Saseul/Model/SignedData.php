<?php

namespace Saseul\Model;

use Util\Hasher;

class SignedData
{
    public $data;
    public $public_key;
    public $signature;
    public $hash;

    public $cid;
    public $type;
    public $timestamp;
    public $attributes = [];
    public $cached_universal = [];
    public $cached_local = [];

    public function attributes(string $key, $value = null)
    {
        if (!is_null($value)) {
            $this->attributes[$key] = $value;
        }

        return ($this->attributes[$key] ?? ($this->data[$key] ?? null));
    }

    public function cachedUniversal(string $key, $value = null)
    {
        if (!is_null($value)) {
            $this->cached_universal[$key] = $value;
        }

        return ($this->cached_universal[$key] ?? null);
    }

    public function cachedLocal(string $key, $value = null)
    {
        if (!is_null($value)) {
            $this->cached_local[$key] = $value;
        }

        return ($this->cached_local[$key] ?? null);
    }

    public function obj(): array
    {
        return [
            'data' => $this->data,
            'public_key' => $this->public_key,
            'signature' => $this->signature
        ];
    }

    public function hash(): string
    {
        return Hasher::timeHash(Hasher::hash($this->data), (int) $this->timestamp);
    }

    public function json(): string
    {
        return json_encode($this->obj());
    }

    public function size(): int
    {
        return strlen($this->json());
    }
}