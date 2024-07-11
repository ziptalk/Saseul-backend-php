<?php

namespace Saseul\Model;

use Util\Clock;

class SignedRequest extends SignedData
{
    public function __construct(array $item = [])
    {
        $this->data = $item['request'] ?? null;
        $this->public_key = $item['public_key'] ?? null;
        $this->signature = $item['signature'] ?? null;

        $this->cid = $this->data['cid'] ?? null;
        $this->type = $this->data['type'] ?? null;
        $this->timestamp = $this->data['timestamp'] ?? Clock::utime();
        $this->hash = $this->hash();
    }

    public function validity(?string &$err_msg): bool
    {
        if (is_null($this->data)) {
            $err_msg = 'The request must contain the "request" parameter. ';
            return false;
        }

        if (is_null($this->type)) {
            $err_msg = 'The request must contain the "request.type" parameter. ';
            return false;
        }

        if (!is_string($this->type)) {
            $err_msg = 'Parameter "request.type" must be of string type. ';
            return false;
        }

        return true;
    }

    public function obj(): array
    {
        return [
            'request' => $this->data,
            'public_key' => $this->public_key,
            'signature' => $this->signature
        ];
    }
}