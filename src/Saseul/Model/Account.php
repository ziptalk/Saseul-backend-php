<?php

namespace Saseul\Model;

use Util\Signer;

class Account
{
    private $private_key = '';
    private $public_key = '';
    private $address = '';

    public function __construct(?string $private_key = null) {
        if (is_string($private_key) && Signer::keyValidity($private_key)) {
            $this->privateKey($private_key);
            $this->publicKey(Signer::publicKey($private_key));
            $this->address(Signer::address($this->publicKey()));
        }
    }

    public function privateKey(?string $private_key = null): string
    {
        $this->private_key = $private_key ?? ($this->private_key);

        return $this->private_key;
    }

    public function publicKey(?string $public_key = null): string
    {
        $this->public_key = $public_key ?? ($this->public_key);

        return $this->public_key;
    }

    public function address(?string $address = null): string
    {
        $this->address = $address ?? ($this->address);

        return $this->address;
    }

    public function obj(): array
    {
        return [
            'private_key' => $this->privateKey(),
            'public_key' => $this->publicKey(),
            'address' => $this->address()
        ];
    }
}