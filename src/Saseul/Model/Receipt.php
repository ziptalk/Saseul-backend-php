<?php

namespace Saseul\Model;

use Util\Hasher;
use Util\Signer;

class Receipt {
    # { { height, address }, public_key, signature }

    public $previous_blockhash;
    public $beneficiary;
    public $signed_query;
    public $public_key;
    public $signature;

    public $hash;

    public function __construct(array $initalizer = [])
    {
        $this->previous_blockhash = $initalizer['previous_blockhash'] ?? '';
        $this->beneficiary = $initalizer['beneficiary'] ?? '';
        $this->signed_query = $initalizer['signed_query'] ?? [];
        $this->public_key = $initalizer['public_key'] ?? '';
        $this->signature = $initalizer['signature'] ?? '';

        $this->hash = Hasher::hash($this->receiptHeader());
    }

    public function obj(): array
    {
        return [
            'previous_blockhash' => $this->previous_blockhash,
            'receipt_hash' => $this->hash,
            'beneficiary' => $this->beneficiary,
            'signed_query' => $this->signed_query,
            'public_key' => $this->public_key,
            'signature' => $this->signature
        ];
    }

    public function json()
    {
        return json_encode($this->obj());
    }

    public function receiptHeader(): array
    {
        return [
            'previous_blockhash' => $this->previous_blockhash,
            'beneficiary' => $this->beneficiary,
            'signed_query' => $this->signed_query
        ];
    }

    public function signer(): ?string
    {
        $query_public_key = $this->signed_query['public_key'] ?? null;

        if (is_null($query_public_key)) {
            return null;
        }

        return Signer::address($query_public_key);
    }

    public function validity(): bool
    {
        if (!is_array($this->signed_query)) {
            return false;
        }

        if (!is_string($this->public_key)) {
            return false;
        }

        if (!is_string($this->signature)) {
            return false;
        }

        if (!Signer::signatureValidity(Hasher::hash($this->receiptHeader()), $this->public_key, $this->signature)) {
            return false;
        }

        $query = $this->signed_query['query'] ?? [];
        $query_public_key = $this->signed_query['public_key'] ?? '';
        $query_signature = $this->signed_query['signature'] ?? '';

        if (!is_array($query)) {
            return false;
        }

        if (!is_string($query_public_key)) {
            return false;
        }

        if (!is_string($query_signature)) {
            return false;
        }

        if (!Signer::signatureValidity(Hasher::hash($query), $query_public_key, $query_signature)) {
            return false;
        }

        $query_previous_blockhash = $query['previous_blockhash'] ?? '';
        $query_address = $query['address'] ?? '';

        if (!is_string($query_previous_blockhash)) {
            return false;
        }

        if (!is_string($query_address)) {
            return false;
        }

        if (Signer::address($this->public_key) !== $query_address) {
            return false;
        }

        if ($this->previous_blockhash !== $query_previous_blockhash) {
            return false;
        }

        return true;
    }
}