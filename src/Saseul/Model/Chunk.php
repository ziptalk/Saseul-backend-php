<?php

namespace Saseul\Model;

use Util\Hasher;
use Util\Signer;

class Chunk {

    public $previous_blockhash;
    public $s_timestamp;
    public $transactions;

    public $chunk_hash;
    public $public_key;
    public $signature;

    public function __construct(array $initial_info = [])
    {
        $this->previous_blockhash = $initial_info['previous_blockhash'] ?? null;
        $this->s_timestamp = (int) ($initial_info['s_timestamp'] ?? 0);
        $this->transactions = $initial_info['transactions'] ?? null;

        $this->chunk_hash = $initial_info['chunk_hash'] ?? null;
        $this->public_key = $initial_info['public_key'] ?? null;
        $this->signature = $initial_info['signature'] ?? null;
    }

    public function signer(): string
    {
        return Signer::address($this->public_key) ?? '';
    }

    public function chunkRoot(): string
    {
        return Hasher::merkleRoot($this->thashs());
    }

    public function thashs(): array
    {
//        $thashs = array_keys($this->transactions);
//        sort($thashs);
        $thashs = $this->transactions;
        sort($thashs);

        return $thashs;
    }

    public function signChunk(Account $account): void
    {
        $chunk_root = $this->chunkRoot();

        $this->chunk_hash = Hasher::timeHash($this->previous_blockhash. $chunk_root, $this->s_timestamp);
        $this->public_key = $account->publicKey();
        $this->signature = Signer::signature($this->chunk_hash, $account->privateKey());
    }

    public function hashValidity(): bool
    {
        $chunk_root = $this->chunkRoot();
        $chunk_hash = Hasher::timeHash($this->previous_blockhash. $chunk_root, $this->s_timestamp);

        return $chunk_hash === $this->chunk_hash;
    }

    public function signatureValidity(): bool
    {
        return Signer::signatureValidity($this->chunk_hash, $this->public_key, $this->signature);
    }

    public function structureValidity(): bool
    {
        return is_string($this->previous_blockhash) &&
            is_int($this->s_timestamp) &&
            is_array($this->transactions) &&
            is_string($this->chunk_hash) &&
            is_string($this->public_key) &&
            is_string($this->signature);
    }

    public function validity(): bool
    {
        return $this->structureValidity() &&
            $this->hashValidity() &&
            $this->signatureValidity();
    }

    public function obj(): array
    {
        return [
            'previous_blockhash' => $this->previous_blockhash,
            'transactions' => $this->transactions,
            'chunk_hash' => $this->chunk_hash,
            's_timestamp' => $this->s_timestamp,
            'public_key' => $this->public_key,
            'signature' => $this->signature,
        ];
    }

    public function json(): string
    {
        return json_encode($this->obj());
    }
}