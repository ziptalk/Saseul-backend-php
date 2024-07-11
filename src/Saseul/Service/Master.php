<?php

namespace Saseul\Service;

use Core\Logger;
use Core\Process;
use Core\Service;
use IPC\TCPCommand;
use IPC\TCPSocket;
use Saseul\Config;
use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\Data\Tracker;
use Saseul\DataSource\Database;
use Saseul\DataSource\PoolClient;
use Saseul\Staff\ProcessManager;
use Util\Timer;

class Master extends Service
{
    protected $_iterate = 1000;
    protected $socket;

    public function __construct()
    {
        if (ProcessManager::isRunning(ProcessManager::MASTER)) {
            Logger::log('Master process is already running. ');
            $this->stop();
        }

        cli_set_process_title('saseul: master');

        Tracker::init();
        ProcessManager::save(ProcessManager::MASTER);

        $this->socket = new TCPSocket();
    }

    public function __destruct()
    {
        if (ProcessManager::pid(ProcessManager::MASTER) === getmypid()) {
            Logger::log('Master process has been successfully removed. ');
            ProcessManager::delete(ProcessManager::MASTER);
        }
    }

    public function init()
    {
        $t = new Timer();
        Logger::log('Master Process Initializing... ');

        $this->load();

        # add commands;
        $this->socket->addListener('stop', [ $this, 'safeStop' ]);
        $this->socket->addListener('reload', [ $this, 'safeReload' ]);

        Logger::log('Master Process: Status Initializing Time:  '. ($t->check() / 1000000). 's');

        # tasks;
        $this->addRoutine([ $this, 'checkProcess' ], 5 * 1000000);
        $this->addRoutine([ $this, 'checkDB' ], 60 * 1000000);
        $this->addRoutine([ $this, 'logRotation' ], 3600 * 1000000);
        $this->checkProcess();
        $this->checkDB();
        $this->logRotation();

        # listen;
        $this->socket->listen(Config::MASTER_ADDR, Config::MASTER_PORT);

        PoolClient::instance()->setPolicy('mining', Config::$_mining);
        Logger::log('Master process started. ');
    }

    public function safeStop(?TCPCommand $command = null): bool
    {
        $this->addQueue([ $this, 'stop' ]);
        return true;
    }

    public function safeReload(?TCPCommand $command = null): bool
    {
        $this->addQueue([ $this, 'reload' ]);
        return true;
    }

    public function main()
    {
        $this->socketOperation();
        parent::main();
    }

    protected function socketOperation(): void
    {
        if (!$this->socket->isListening()) {
            return;
        }

        $this->socket->selectOperation();
        $this->socket->readOperation();

        while ($command = $this->socket->popReceivedCommandQueue()) {
            $result = $this->socket->run($command);
            $this->socket->sendResponse($result, $command);
        }

        $this->socket->writeOperation();
    }

    public function load()
    {
        Process::spawn(SERVICE_BIN, 'DataPool');

        while (!PoolClient::instance()->isRunning()) {
            sleep(1);
        }

        # policy;
        PoolClient::instance()->setPolicy('chain_maker', Config::$_consensus);
        PoolClient::instance()->setPolicy('resource_miner', Config::$_consensus);
        PoolClient::instance()->setPolicy('collector', Config::$_collect);
        PoolClient::instance()->setPolicy('peer_searcher', Config::$_collect);

        PoolClient::instance()->setPolicy('main_chain_waiting', false);
        PoolClient::instance()->setPolicy('resource_chain_waiting', false);
    }

    public function stop()
    {
        ProcessManager::kill(ProcessManager::RESOURCE_MINER);
        ProcessManager::kill(ProcessManager::CHAIN_MAKER);
        ProcessManager::kill(ProcessManager::COLLECTOR);
        ProcessManager::kill(ProcessManager::PEER_SEARCHER);
        ProcessManager::kill(ProcessManager::DATA_POOL);
        Logger::log('The master process has stopped. ');
        exit;
    }

    public function reload(): bool
    {
        $mining = (bool) (PoolClient::instance()->getPolicy('mining') ?? Config::$_mining);

        $fixed_height = Chain::fixedHeight();
        $reload_point = ResourceChain::instance()->block($fixed_height);
        Logger::log("Master: Reload - reload_point: $fixed_height");

        PoolClient::instance()->setPolicy('chain_maker', false);
        PoolClient::instance()->setPolicy('resource_miner', false);
        PoolClient::instance()->setPolicy('collector', false);
        PoolClient::instance()->setPolicy('peer_searcher', false);

        ProcessManager::kill(ProcessManager::RESOURCE_MINER);
        ProcessManager::kill(ProcessManager::CHAIN_MAKER);
        ProcessManager::kill(ProcessManager::COLLECTOR);
        ProcessManager::kill(ProcessManager::PEER_SEARCHER);
        ProcessManager::kill(ProcessManager::DATA_POOL);

        do {
            Logger::log('Master: Reloading processes... ');

            $resource_miner = ProcessManager::isRunning(ProcessManager::RESOURCE_MINER);
            $chain_maker = ProcessManager::isRunning(ProcessManager::CHAIN_MAKER);
            $collector = ProcessManager::isRunning(ProcessManager::COLLECTOR);
            $peer_searcher = ProcessManager::isRunning(ProcessManager::PEER_SEARCHER);
            $data_pool = ProcessManager::isRunning(ProcessManager::DATA_POOL);

            sleep(1);

        } while ($resource_miner || $chain_maker || $collector || $peer_searcher || $data_pool);

        ResourceChain::instance()->remove($reload_point->height + 1);
        MainChain::instance()->remove($reload_point->main_height + 1);

        $this->load();

        PoolClient::instance()->setPolicy('mining', $mining);
        return true;
    }

    public function checkProcess(): void
    {
        $policy = PoolClient::instance()->getPolicy();

        if (is_array($policy)) {
            $rm_policy = $policy['resource_miner'] ?? false;
            $cm_policy = $policy['chain_maker'] ?? false;
            $col_policy = $policy['collector'] ?? false;
            $ps_policy = $policy['peer_searcher'] ?? false;

            $rm_running = ProcessManager::isRunning(ProcessManager::RESOURCE_MINER);
            $cm_running = ProcessManager::isRunning(ProcessManager::CHAIN_MAKER);
            $col_running = ProcessManager::isRunning(ProcessManager::COLLECTOR);
            $ps_running = ProcessManager::isRunning(ProcessManager::PEER_SEARCHER);

            if ($rm_policy && !$rm_running) {
                Process::spawn(SERVICE_BIN, 'ResourceMiner');
            } elseif (!$rm_policy && $rm_running) {
                Logger::log('Resource miner process end. ');
                ProcessManager::kill(ProcessManager::RESOURCE_MINER);
            }

            if ($cm_policy && !$cm_running) {
                Process::spawn(SERVICE_BIN, 'ChainMaker');
            } elseif (!$cm_policy && $cm_running) {
                Logger::log('Chain maker process end. ');
                ProcessManager::kill(ProcessManager::CHAIN_MAKER);
            }

            if ($col_policy && !$col_running) {
                Process::spawn(SERVICE_BIN, 'Collector');
            } elseif (!$col_policy && $col_running) {
                Logger::log('Collector process end. ');
                ProcessManager::kill(ProcessManager::COLLECTOR);
            }

            if ($ps_policy && !$ps_running) {
                Process::spawn(SERVICE_BIN, 'PeerSearcher');
            } elseif (!$ps_policy && $ps_running) {
                Logger::log('Peer searcher process end. ');
                ProcessManager::kill(ProcessManager::PEER_SEARCHER);
            }
        }
    }

    public function checkDB(): void
    {
        if (Config::$_database) {
            if (!Database::instance()->isConnect()) {
                $this->stop();
            }
        }
    }

    public function logRotation() {
        Logger::backup();
        Logger::cleanOldLog();
    }
}