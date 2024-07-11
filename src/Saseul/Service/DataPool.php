<?php

namespace Saseul\Service;

use Core\Logger;
use Core\Service;
use Saseul\Config;
use Saseul\Data\Bunch;
use Saseul\Data\MainChain;
use Saseul\DataSource\BunchIndex;
use Saseul\DataSource\StatusIndex;
use Saseul\DataSource\PoolCommands;
use Saseul\Staff\ProcessManager;
use IPC\UDPSocket;
use Util\Filter;
use Util\Parser;
use Util\Timer;

class DataPool extends Service
{
    protected $socket;
    protected $bunch_index;
    protected $status_index;

    protected $peer_requests = [];
    protected $policy = [];

    public function __construct()
    {
        if (ProcessManager::isRunning(ProcessManager::DATA_POOL)) {
            Logger::log('Data pool process is already running. ');
            exit;
        }

        cli_set_process_title('saseul: mempool');

        ProcessManager::save(ProcessManager::DATA_POOL);

        $this->socket = new UDPSocket();
        $this->bunch_index = new BunchIndex();
        $this->status_index = new StatusIndex();
    }

    public function __destruct()
    {
        if (ProcessManager::pid(ProcessManager::DATA_POOL) === getmypid()) {
            Logger::log('Data pool process has been successfully removed. ');
            ProcessManager::delete(ProcessManager::DATA_POOL);
        }
    }

    public function init(): void
    {
        # commands;
        PoolCommands::load();

        Logger::log('DataPool Initializing... ');
        $t = new Timer();

        # init: bunch;
        Logger::log('Starting to reset broadcast data.');
        Bunch::reset();
        Logger::log('Broadcast data has been reset successfully. ');

        # init: db;
        Logger::log('Starting blockchain data synchronization to the database. ');
        MainChain::instance()->makeDB();
        Logger::log('Blockchain data synchronization to the database has been completed. ');

        # init: status;
        Logger::log('Starting to initialize status data. ');
        $this->status_index->load();
        Logger::log('Status data has been initialized. ');

        # base;
        $this->addListener(PoolCommands::IS_RUNNING, [ $this, 'isRunning']);

        # tracker;
        $this->addListener(PoolCommands::ADD_PEER_REQUEST, [ $this, 'addPeerRequest']);
        $this->addListener(PoolCommands::PEER_REQUESTS, [ $this, 'peerRequests']);
        $this->addListener(PoolCommands::DRAIN_PEER_REQUESTS, [ $this, 'drainPeerRequests']);

        # policy;
        $this->addListener(PoolCommands::SET_POLICY, [ $this, 'setPolicy']);
        $this->addListener(PoolCommands::GET_POLICY, [ $this, 'getPolicy']);

        # bunch;
        $this->addListener(PoolCommands::ADD_TX_INDEX, [ $this->bunch_index, 'addTxIndex']);
        $this->addListener(PoolCommands::EXISTS_TX, [ $this->bunch_index, 'existsTx']);
        $this->addListener(PoolCommands::INFO_TXS, [ $this->bunch_index, 'infoTxs']);
        $this->addListener(PoolCommands::REMOVE_TXS, [ $this->bunch_index, 'removeTxs']);
        $this->addListener(PoolCommands::FLUSH_TXS, [ $this->bunch_index, 'flushTxs']);

        $this->addListener(PoolCommands::ADD_CHUNK_INDEX, [ $this->bunch_index, 'addChunkIndex']);
        $this->addListener(PoolCommands::COUNT_CHUNKS, [ $this->bunch_index, 'countChunks']);
        $this->addListener(PoolCommands::REMOVE_CHUNKS, [ $this->bunch_index, 'removeChunks']);

        $this->addListener(PoolCommands::ADD_HYPOTHESIS_INDEX, [ $this->bunch_index, 'addHypothesisIndex']);
        $this->addListener(PoolCommands::COUNT_HYPOTHESES, [ $this->bunch_index, 'countHypotheses']);
        $this->addListener(PoolCommands::REMOVE_HYPOTHESES, [ $this->bunch_index, 'removeHypotheses']);

        $this->addListener(PoolCommands::ADD_RECEIPT_INDEX, [ $this->bunch_index, 'addReceiptIndex']);
        $this->addListener(PoolCommands::COUNT_RECEIPTS, [ $this->bunch_index, 'countReceipt']);
        $this->addListener(PoolCommands::REMOVE_RECEIPTS, [ $this->bunch_index, 'removeReceipts']);

        # status;
        $this->addListener(PoolCommands::UNIVERSAL_INDEXES, [ $this->status_index, 'universalIndexes']);
        $this->addListener(PoolCommands::ADD_UNIVERSAL_INDEXES, [ $this->status_index, 'addUniversalIndexes']);

        $this->addListener(PoolCommands::LOCAL_INDEXES, [ $this->status_index, 'localIndexes']);
        $this->addListener(PoolCommands::ADD_LOCAL_INDEXES, [ $this->status_index, 'addLocalIndexes']);

        $this->addListener(PoolCommands::SEARCH_UNIVERSAL_INDEXES, [ $this->status_index, 'searchUniversalIndexes']);
        $this->addListener(PoolCommands::SEARCH_LOCAL_INDEXES, [ $this->status_index, 'searchLocalIndexes']);

        $this->addListener(PoolCommands::COUNT_UNIVERSAL_INDEXES, [ $this->status_index, 'countUniversalIndexes']);
        $this->addListener(PoolCommands::COUNT_LOCAL_INDEXES, [ $this->status_index, 'countLocalIndexes']);

        # test;
        $this->addListener(PoolCommands::TEST, [ $this, 'test']);
        Logger::log('DataPool Initializing Time: '. ($t->check() / 1000000). 's');

        # socket ready;
        $this->socket->create(Config::DATA_POOL_ADDR, Config::DATA_POOL_PORT);
        $this->socket->bind();
    }

    public function addListener(string $command, callable $func)
    {
        $this->socket->addListener(PoolCommands::ref($command), $func);
    }

    public function main(): void
    {
        $this->socket->listen();
    }

    public function test($value)
    {
        if ($value === 'local') {
            return $this->status_index->local_indexes;
        } else {
            return $this->status_index->universal_indexes;
        }
    }

    # base;
    public function isRunning(): bool
    {
        return true;
    }

    # tracker;
    public function addPeerRequest($host): bool
    {
        if (is_string($host) && Filter::isPublicHost($host) &&
            strlen(serialize($this->peer_requests)) < ($this->socket->mtu() - 1024)) {
            $this->peer_requests[] = Parser::endpoint($host);
            return true;
        }

        return false;
    }

    public function peerRequests(): array
    {
        return $this->peer_requests;
    }

    public function drainPeerRequests(): array
    {
        $resp = $this->peer_requests;
        $this->peer_requests = [];
        return $resp;
    }

    # policy;
    public function setPolicy($data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        $target = $data['target'];
        $policy = $data['policy'];

        $this->policy[$target] = $policy;
        return true;
    }

    public function getPolicy($target)
    {
        if (!is_string($target)) {
            return $this->policy;
        }

        return $this->policy[$target] ?? false;
    }
}