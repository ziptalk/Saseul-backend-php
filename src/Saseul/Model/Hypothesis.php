<?php

namespace Saseul\Model;

use Util\Hasher;
use Util\Signer;

class Hypothesis {
    public $previous_blockhash;
    public $s_timestamp;
    public $thashs;
    public $chunks;

    public $hypothesis_hash;
    public $public_key;
    public $signature;

    public function __construct(array $initial_info = [])
    {
        $this->previous_blockhash = $initial_info['previous_blockhash'] ?? '';
        $this->thashs = $initial_info['thashs'] ?? [];
        $this->chunks = $initial_info['chunks'] ?? [];
        $this->s_timestamp = $initial_info['s_timestamp'] ?? 0;

        $this->hypothesis_hash = $initial_info['hypothesis_hash'] ?? '';
        $this->public_key = $initial_info['public_key'] ?? '';
        $this->signature = $initial_info['signature'] ?? '';
    }

    public function signer(): string
    {
        return Signer::address($this->public_key);
    }

    public function hypothesisRoot(): string
    {
        sort($this->thashs);

        return Hasher::merkleRoot($this->thashs);
    }

    public function hypothesisHash(): string
    {
        return Hasher::hash($this->previous_blockhash. $this->hypothesisRoot());
    }

    public function signHypothesis(Account $account): void
    {
        $this->hypothesis_hash = $this->hypothesisHash();
        $this->public_key = $account->publicKey();
        $this->signature = Signer::signature($this->hypothesis_hash, $account->privateKey());
    }

    public function signatureValidity(): bool
    {
        return Signer::signatureValidity($this->hypothesis_hash, $this->public_key, $this->signature);
    }

    public function hypothesisValidity(): bool
    {
        return $this->hypothesis_hash === $this->hypothesisHash();
    }

    public function structureValidity(): bool
    {
        return is_string($this->previous_blockhash) && is_int($this->s_timestamp) &&
            is_array($this->thashs) && is_array($this->chunks) &&
            is_string($this->hypothesis_hash) &&
            is_string($this->public_key) && is_string($this->signature);
    }

    public function validity(): bool
    {
        return $this->structureValidity() &&
            $this->hypothesisValidity() &&
            $this->signatureValidity();
    }

    public function base(): array
    {
        return [
            'hypothesis_hash' => $this->hypothesis_hash,
            's_timestamp' => $this->s_timestamp,
            'public_key' => $this->public_key,
            'signature' => $this->signature,
        ];
    }

    public function seal(): array
    {
        return $this->base();
    }

    public function minimal(): array
    {
        $result = $this->base();
        $result['previous_blockhash'] = $this->previous_blockhash;
        $result['chunks'] = $this->chunks;

        return $result;
    }

    public function json(): string
    {
        return json_encode($this->minimal());
    }
}