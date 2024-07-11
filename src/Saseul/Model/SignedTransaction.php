<?php

namespace Saseul\Model;

use Util\Signer;

class SignedTransaction extends SignedData
{
    public function __construct(array $item = [])
    {
        $this->data = $item['transaction'] ?? null;
        $this->public_key = $item['public_key'] ?? null;
        $this->signature = $item['signature'] ?? null;

        $this->cid = $this->data['cid'] ?? null;
        $this->type = $this->data['type'] ?? null;
        $this->timestamp = $this->data['timestamp'] ?? null;
        $this->hash = $this->hash();
    }

    public function validity(?string &$err_msg = ''): bool
    {
        if (is_null($this->data)) {
            $err_msg = 'The signed transaction must contain the "transaction" parameter. ';
            return false;
        }

        if (is_null($this->public_key)) {
            $err_msg = 'The signed transaction must contain the "public_key" parameter. ';
            return false;
        }

        if (!is_string($this->public_key)) {
            $err_msg = 'Parameter "public_key" must be of string type. ';
            return false;
        }

        if (!Signer::keyValidity($this->public_key)) {
            $err_msg = 'Invalid public key: '. $this->public_key;
            return false;
        }

        if (is_null($this->signature)) {
            $err_msg = 'The signed transaction must contain the "signature" parameter. ';
            return false;
        }

        if (!is_string($this->signature)) {
            $err_msg = 'Parameter "signature" must be of string type. ';
            return false;
        }

        if (is_null($this->type)) {
            $err_msg = 'The signed transaction must contain the "transaction.type" parameter. ';
            return false;
        }

        if (!is_string($this->type)) {
            $err_msg = 'Parameter "transaction.type" must be of string type. ';
            return false;
        }

        if (is_null($this->timestamp)) {
            $err_msg = 'The signed transaction must contain the "transaction.timestamp" parameter. ';
            return false;
        }

        if (!is_int($this->timestamp)) {
            $err_msg = 'Parameter "transaction.timestamp" must be of integer type. ';
            return false;
        }

        if (!Signer::signatureValidity($this->hash, $this->public_key, $this->signature)) {
            $err_msg = 'Invalid signature: '. $this->signature. ' (transaction hash: '. $this->hash. ')';
            return false;
        }

        return true;
    }

    public function obj(): array
    {
        return [
            'transaction' => $this->data,
            'public_key' => $this->public_key,
            'signature' => $this->signature
        ];
    }
}