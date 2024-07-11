<?php

namespace Saseul\DataSource;

use IPC\UDPClient;
use Saseul\Config;

class PoolClient
{
    protected static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected $client;
    protected $mode;

    public function __construct()
    {
        $this->client = new UDPClient();
        $this->client->create(Config::DATA_POOL_ADDR, Config::DATA_POOL_PORT);
        $this->mode('once');

        PoolCommands::load();
    }

    public function mode(string $type) {
        $this->mode = $type;
    }
    
    public function send(string $type, $data = null)
    {
        $command = PoolCommands::ref($type);

        if (!is_null($command)) {
            if ($this->mode === 'once') {
                return $this->client->once($command, $data);
            } else {
                return $this->client->rewind($command, $data);
            }
        }

        return null;
    }

    public function array($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        return [];
    }

    # base
    public function isRunning(): bool
    {
        return (bool) $this->send(PoolCommands::IS_RUNNING);
    }

    # tracker
    public function addPeerRequest($host): bool
    {
        return (bool) $this->send(PoolCommands::ADD_PEER_REQUEST, $host);
    }

    public function peerRequests(): array
    {
        return $this->array($this->send(PoolCommands::PEER_REQUESTS));
    }

    public function drainPeerRequests(): array
    {
        return $this->array($this->send(PoolCommands::DRAIN_PEER_REQUESTS));
    }

    # policy
    public function setPolicy(string $target, bool $policy): bool
    {
        return (bool) $this->send(PoolCommands::SET_POLICY, [
            'target' => $target,
            'policy' => $policy
        ]);
    }

    public function getPolicy(?string $target = null)
    {
        return $this->send(PoolCommands::GET_POLICY, $target);
    }

    # chunks
    public function addTxIndex(array $data): bool
    {
        return (bool) $this->send(PoolCommands::ADD_TX_INDEX, $data);
    }

    public function existsTx(string $hash): bool
    {
        return (bool) $this->send(PoolCommands::EXISTS_TX, $hash);
    }

    public function infoTxs(): array
    {
        return $this->array($this->send(PoolCommands::INFO_TXS));
    }

    public function removeTxs(int $utime): bool
    {
        return (bool) $this->send(PoolCommands::REMOVE_TXS, $utime);
    }

    public function flushTxs(): bool
    {
        return (bool) $this->send(PoolCommands::FLUSH_TXS);
    }

    public function addChunkIndex(array $data): bool
    {
        return (bool) $this->send(PoolCommands::ADD_CHUNK_INDEX, $data);
    }

    public function countChunks(string $round_key): int
    {
        return (int) $this->send(PoolCommands::COUNT_CHUNKS, $round_key);
    }

    public function removeChunks(string $round_key): bool
    {
        return (bool) $this->send(PoolCommands::REMOVE_CHUNKS, $round_key);
    }

    public function addHypothesisIndex(array $data): bool
    {
        return (bool) $this->send(PoolCommands::ADD_HYPOTHESIS_INDEX, $data);
    }

    public function countHypotheses($round_key): int
    {
        return (int) $this->send(PoolCommands::COUNT_HYPOTHESES, $round_key);
    }

    public function removeHypotheses(string $round_key): bool
    {
        return (bool) $this->send(PoolCommands::REMOVE_HYPOTHESES, $round_key);
    }

    public function addReceiptIndex(array $data): bool
    {
        return (bool) $this->send(PoolCommands::ADD_RECEIPT_INDEX, $data);
    }

    public function countReceipts($round_key): int
    {
        return (int) $this->send(PoolCommands::COUNT_RECEIPTS, $round_key);
    }

    public function removeReceipts(string $round_key): bool
    {
        return (bool) $this->send(PoolCommands::REMOVE_RECEIPTS, $round_key);
    }

    # status
    public function universalIndexes(array $keys): array
    {
        return $this->array($this->send(PoolCommands::UNIVERSAL_INDEXES, $keys));
    }

    public function addUniversalIndexes(array $indexes): bool
    {
        return (bool) $this->send(PoolCommands::ADD_UNIVERSAL_INDEXES, $indexes);
    }

    public function searchUniversalIndexes(string $prefix, int $page = 0, int $count = 50): array
    {
        return $this->array($this->send(PoolCommands::SEARCH_UNIVERSAL_INDEXES, [$prefix, $page, $count]));
    }

    public function countUniversalIndexes(string $prefix): int
    {
        return (int) ($this->send(PoolCommands::COUNT_UNIVERSAL_INDEXES, $prefix) ?? 0);
    }

    public function localIndexes(array $keys): array
    {
        return $this->array($this->send(PoolCommands::LOCAL_INDEXES, $keys));
    }

    public function addLocalIndexes(array $indexes): bool
    {
        return (bool) $this->send(PoolCommands::ADD_LOCAL_INDEXES, $indexes);
    }

    public function searchLocalIndexes(string $prefix, int $page = 0, int $count = 50): array
    {
        return $this->array($this->send(PoolCommands::SEARCH_LOCAL_INDEXES, [$prefix, $page, $count]));
    }

    public function countLocalIndexes(string $prefix): int
    {
        return (int) ($this->send(PoolCommands::COUNT_LOCAL_INDEXES, $prefix) ?? 0);
    }

    public function test($value = null)
    {
        return $this->send(PoolCommands::TEST, $value);
    }
}