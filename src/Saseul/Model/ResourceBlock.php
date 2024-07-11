<?php

namespace Saseul\Model;

use Saseul\Config;
use Saseul\Data\Env;
use Saseul\Data\ResourceChain;
use Util\Hasher;
use Util\Math;
use Util\Parser;
use Util\Signer;

class ResourceBlock {

    public $height;
    public $previous_blockhash;
    public $blockhash;
    public $nonce;
    public $timestamp;
    public $validator;
    public $miner;

    public $main_height;
    public $main_blockhash;
    public $receipts;

    public $difficulty;

    public function __construct(array $initial_info = [])
    {
        $this->height = $initial_info['height'] ?? 0;
        $this->previous_blockhash = $initial_info['previous_blockhash'] ?? '';
        $this->blockhash = $initial_info['blockhash'] ?? '';
        $this->nonce = $initial_info['nonce'] ?? '';
        $this->timestamp = (int) ($initial_info['timestamp'] ?? 0);
        $this->validator = $initial_info['validator'] ?? '';
        $this->miner = $initial_info['miner'] ?? '';

        $this->main_height = $initial_info['main_height'] ?? 0;
        $this->main_blockhash = $initial_info['main_blockhash'] ?? '';
        $this->receipts = $initial_info['receipts'] ?? [];

        $this->difficulty = $initial_info['difficulty'] ?? Config::DEFAULT_DIFFICULTY;
    }

    public function blockValidityLegacy(): bool
    {
        return $this->structureValidity() &&
            $this->hashValidity() &&
            $this->nonceValidity() &&
            $this->receiptValidity() &&
            $this->genesisValidity();
    }

    public function blockValidity(): bool
    {
        return $this->structureValidity() &&
            $this->hashValidity() &&
            $this->nonceValidity() &&
            $this->receiptValidity() &&
            $this->difficultyValidity() &&
            $this->genesisValidity();
    }

    public function difficultyValidity(): bool
    {
        return ResourceChain::instance()->difficulty($this->height) === $this->difficulty;
    }

    public function receiptValidity(): bool
    {
        foreach ($this->receipts as $receipt) {
            if (is_array($receipt)) {
                $receipt = new Receipt($receipt);

                if (!$receipt->validity()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function genesisValidity(): bool
    {
        if ($this->height <= Config::RESOURCE_CONFIRM_COUNT) {
            return ($this->validator === Config::$_genesis_address);
        }

        return true;
    }

    public function nonceValidity(): bool
    {
        return $this->hashLimit($this->difficulty) >= $this->blockRoot();
    }

    public function hashValidity(): bool
    {
        return $this->blockhash === $this->blockhash();
    }

    public function structureValidity(): bool
    {
        return is_int($this->height) && is_string($this->blockhash) &&
            is_int($this->timestamp) && is_array($this->receipts) &&
            is_string($this->nonce) && (strlen($this->nonce) <= Hasher::HASH_SIZE) &&
            is_string($this->validator) && is_string($this->miner) &&
            Signer::addressValidity($this->validator) && Signer::addressValidity($this->miner) &&
            is_int($this->main_height) && is_string($this->main_blockhash);
    }

    public function receiptRoot(): string
    {
        return Hasher::merkleRoot($this->receipts);
    }

    public function blockHeader(): string
    {
        return Hasher::hash([
            'height' => $this->height,
            'timestamp' => $this->timestamp,
            'receipt_root' => $this->receiptRoot(),
            'main_height' => $this->main_height,
            'main_blockhash' => $this->main_blockhash,
            'validator' => $this->validator,
            'miner' => $this->miner
        ]);
    }

    public function blockRoot(?string $nonce = null): string
    {
        $nonce = $nonce ?? $this->nonce;

        return Hasher::hash($this->previous_blockhash. $this->blockHeader(). $nonce);
    }

    public function blockhash(): string
    {
        return Hasher::timeHash($this->blockRoot(), $this->timestamp);
    }

    public function hashLimit($difficulty): string
    {
        return str_pad(
            Parser::dechex((Math::div(Config::HASH_COUNT, $difficulty) ?? 0)),
            Hasher::HASH_SIZE, '0', STR_PAD_LEFT
        );
    }

    public function baseObj(): array
    {
        return [
            'height' => $this->height,
            'blockhash' => $this->blockhash,
            'previous_blockhash' => $this->previous_blockhash,
            'nonce' => $this->nonce,
            'timestamp' => $this->timestamp,
            'difficulty' => $this->difficulty,
            'main_height' => $this->main_height,
            'main_blockhash' => $this->main_blockhash,
            'validator' => $this->validator,
            'miner' => $this->miner,
        ];
    }

    public function fullObj(): array
    {
        $obj = $this->baseObj();
        $obj['receipts'] = $this->receipts;

        return $obj;
    }

    public function minimalObj(): array
    {
        $obj = $this->baseObj();
        $obj['receipt_count'] = count($this->receipts);

        return $obj;
    }

    public function json(): string
    {
        return json_encode($this->fullObj());
    }
}