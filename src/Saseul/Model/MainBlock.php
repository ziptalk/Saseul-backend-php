<?php

namespace Saseul\Model;

use Saseul\Config;
use Util\Hasher;

class MainBlock {

    public $height;
    public $transactions;
    public $s_timestamp;
    public $seal;
    public $universal_updates;
    public $local_updates;

    public $previous_blockhash;
    public $blockhash;
    public $validators;

    public function __construct(array $initial_info = [])
    {
        $this->height = $initial_info['height'] ?? 0;
        $this->transactions = $initial_info['transactions'] ?? [];
        $this->s_timestamp = (int) ($initial_info['s_timestamp'] ?? 0);
        $this->seal = $initial_info['seal'] ?? [];
        $this->universal_updates = $initial_info['universal_updates'] ?? [];
        $this->local_updates = $initial_info['local_updates'] ?? [];

        $this->previous_blockhash = $initial_info['previous_blockhash'] ?? '';
        $this->blockhash = $initial_info['blockhash'] ?? '';
        $this->validators = $initial_info['validators'] ?? [];
    }

    public function blockRoot(): string
    {
        return Hasher::hash($this->transactionRoot(). $this->updateRoot());
    }

    public function transactionRoot(): string
    {
        return Hasher::merkleRoot($this->thashs());
    }

    public function updateRoot(): string
    {
        return Hasher::merkleRoot($this->uhashs());
    }

    public function blockHeader(): string
    {
        return Hasher::hash([
            'height' => $this->height,
            's_timestamp' => $this->s_timestamp,
            'block_root' => $this->blockRoot()
        ]);
    }

    public function blockhash(): string
    {
        return Hasher::timeHash($this->previous_blockhash. $this->blockHeader(), $this->s_timestamp);
    }

    public function thashs(): array
    {
        $thashs = array_keys($this->transactions);

        sort($thashs);

        return $thashs;
    }

    public function uhashs(): array
    {
        $update_hashs = [];

        foreach ($this->universal_updates as $key => $update) {
            $update_hashs[] = $key. Hasher::hash($update);
        }

        foreach ($this->local_updates as $key => $update) {
            $update_hashs[] = $key. Hasher::hash($update);
        }

        sort($update_hashs);

        return $update_hashs;
    }

    public function makeBlockhash(): void
    {
        $this->blockhash = $this->blockhash();
    }

    public function validity(): bool
    {
        return $this->structureValidity() &&
            $this->hashValidity() &&
            $this->sealValidity();
    }

    public function structureValidity(): bool
    {
        return is_int($this->height) &&
            is_array($this->transactions) &&
            is_int($this->s_timestamp) &&
            is_array($this->seal) &&
            is_string($this->previous_blockhash) &&
            is_string($this->blockhash);
    }

    public function hashValidity(): bool
    {
        return $this->blockhash === $this->blockhash();
    }

    public function sealValidity(): bool
    {
        return $this->sealValidityNew();
    }

    public function sealValidityLegacy(): bool
    {
        $s_timestamps = [];
        $quorum = count($this->validators) * Config::MAIN_CONSENSUS_PER;
        $votes_cast = 0;

        foreach ($this->validators as $validator) {
            if (isset($this->seal[$validator]) && is_array($this->seal[$validator])) {
                $hypothesis = new Hypothesis($this->seal[$validator]);
                $hypothesis->thashs = $this->thashs();
                $hypothesis->previous_blockhash = $this->previous_blockhash;

                if ($hypothesis->validity() && $hypothesis->signer() === $validator) {
                    $s_timestamps[] = $hypothesis->s_timestamp;
                    $votes_cast = $votes_cast + 1;
                }
            }
        }

        if ($votes_cast > $quorum && max($s_timestamps) === $this->s_timestamp) {
            return true;
        }

        return false;
    }

    public function sealValidityNew(): bool
    {
        # 1
        $main = $this->validators[8] ?? null;
        $seals = array_keys($this->seal);

        if (!is_null($main) && in_array($main, $seals)) {
            $hypothesis = new Hypothesis($this->seal[$main]);
            $hypothesis->thashs = $this->thashs();
            $hypothesis->previous_blockhash = $this->previous_blockhash;

            $validity = $hypothesis->validity() && $hypothesis->signer() === $main &&
                $hypothesis->s_timestamp === $this->s_timestamp;

            if ($validity) {
                return true;
            }
        }

        return $this->sealValidityLegacy();
    }

    public function isValidator(string $address): bool
    {
        if (count($this->validators) === 0) {
            return false;
        }

        return in_array($address, $this->validators);
    }

    public function baseObj(): array
    {
        return [
            'height' => $this->height,
            's_timestamp' => $this->s_timestamp,
            'previous_blockhash' => $this->previous_blockhash,
            'blockhash' => $this->blockhash,
        ];
    }

    public function fullObj(): array
    {
        $obj = $this->baseObj();
        $obj['seal'] = $this->seal;
        $obj['transactions'] = $this->transactions;
        $obj['universal_updates'] = $this->universal_updates;
        $obj['local_updates'] = $this->local_updates;

        return $obj;
    }

    public function minimalObj(): array
    {
        $obj = $this->baseObj();
        $obj['transaction_count'] = count($this->transactions);

        return $obj;
    }

    public function json(): string
    {
        return json_encode($this->fullObj());
    }
}