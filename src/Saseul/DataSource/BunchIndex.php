<?php

namespace Saseul\DataSource;

use Saseul\Config;

class BunchIndex
{
    protected $tx_indexes = [];
    protected $chunk_indexes = [];
    protected $hypothesis_indexes = [];
    protected $receipt_indexes = [];

    # chunks;
    public function addTxIndex($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $hash = $data['hash'] ?? '';

        if (isset($this->tx_indexes[$hash])) {
            return false;
        }

        $timestamp = $data['timestamp'];
        $size = $data['size'];

        $tx_count = count($this->tx_indexes);
        $tx_size = array_sum(array_column($this->tx_indexes, 'size'));

        $count_limit = Config::BLOCK_TX_COUNT_LIMIT * 3;
        $size_limit = Config::BLOCK_TX_SIZE_LIMIT * 3;

        if ($tx_count >= $count_limit || $tx_size + $size > $size_limit) {
            return false;
        }

        $this->tx_indexes[$hash] = [
            'timestamp' => $timestamp,
            'size' => $size
        ];

        return true;
    }

    public function existsTx($hash): bool
    {
        if (!is_string($hash)) {
            return false;
        }

        return isset($this->tx_indexes[$hash]);
    }

    public function infoTxs(): array
    {
        return [
            'count' => count($this->tx_indexes),
            'size' => array_sum(array_column($this->tx_indexes, 'size'))
        ];
    }

    public function removeTxs($utime): bool
    {
        if (!is_int($utime)) {
            return false;
        }

        foreach ($this->tx_indexes as $key => $tx_index) {
            $timestamp = $tx_index['timestamp'] ?? 0;

            if ($timestamp < $utime) {
                unset($this->tx_indexes[$key]);
            }
        }

        return true;
    }

    public function flushTxs(): bool
    {
        $this->tx_indexes = [];
        return true;
    }

    public function addChunkIndex($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $round_key = $data['round_key'];
        $signer = $data['signer'];
        $hash = $data['hash'];

        $this->chunk_indexes[$round_key][$signer] = $hash;
        return true;
    }

    public function countChunks($round_key): int
    {
        if (!is_string($round_key)) {
            return 0;
        }

        $chunk_indexes = $this->chunk_indexes[$round_key] ?? [];

        return count($chunk_indexes);
    }

    public function removeChunks($round_key): bool
    {
        if (!is_string($round_key)) {
            return false;
        }

        foreach ($this->chunk_indexes as $key => $chunk_index) {
            if ($round_key !== $key) {
                unset($this->chunk_indexes[$key]);
            }
        }

        return true;
    }

    public function addHypothesisIndex($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $round_key = $data['round_key'];
        $signer = $data['signer'];
        $hash = $data['hash'];

        $this->hypothesis_indexes[$round_key][$signer] = $hash;
        return true;
    }

    public function countHypotheses($round_key): int
    {
        if (!is_string($round_key)) {
            return 0;
        }

        $hypothesis_indexes = $this->hypothesis_indexes[$round_key] ?? [];

        return count($hypothesis_indexes);
    }

    public function removeHypotheses($round_key): bool
    {
        if (!is_string($round_key)) {
            return false;
        }

        foreach ($this->hypothesis_indexes as $key => $hypothesis_index) {
            if ($round_key !== $key) {
                unset($this->hypothesis_indexes[$key]);
            }
        }

        return true;
    }

    public function addReceiptIndex($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $round_key = $data['round_key'];
        $signer = $data['signer'];
        $hash = $data['hash'];

        if (isset($this->receipt_indexes[$round_key][$signer]) || count($this->receipt_indexes) >= Config::RECEIPT_COUNT_LIMIT) {
            return false;
        }

        $this->receipt_indexes[$round_key][$signer] = $hash;
        return true;
    }

    public function countReceipt($round_key): int
    {
        if (!is_string($round_key)) {
            return false;
        }

        $receipt_indexes = $this->receipt_indexes[$round_key] ?? [];

        return count($receipt_indexes);
    }

    public function removeReceipts($round_key): bool
    {
        if (!is_string($round_key)) {
            return false;
        }

        foreach ($this->receipt_indexes as $key => $receipt_index) {
            if ($round_key !== $key) {
                unset($this->receipt_indexes[$key]);
            }
        }

        return true;
    }
}