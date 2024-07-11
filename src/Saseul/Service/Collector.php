<?php

namespace Saseul\Service;

use Core\Logger;
use Core\Service;
use Saseul\Config;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Data\Tracker;
use Saseul\DataSource\PoolClient;
use Saseul\Staff\Observer;
use Saseul\Staff\ProcessManager;

class Collector extends Service
{
    protected $_iterate = 100000;

    public function __construct()
    {
        if (Config::$_environment === 'process') {
            if (ProcessManager::isRunning(ProcessManager::COLLECTOR)) {
                Logger::log('The collector process is already running. ');
                exit;
            }

            cli_set_process_title('saseul: data_collector');
            ProcessManager::save(ProcessManager::COLLECTOR);
        }

        PoolClient::instance()->mode('rewind');
    }

    public function __destruct()
    {
        if (ProcessManager::pid(ProcessManager::COLLECTOR) === getmypid()) {
            Logger::log('Collector process has been successfully removed. ');
            ProcessManager::delete(ProcessManager::COLLECTOR);
        }
    }

    public function init()
    {
        $this->addRoutine([ $this, 'collect'], 300000);

        Logger::log('The collector process has started. ');
    }

    public function collect(): bool
    {
        $peers = Tracker::getPeers();
        $hosts = Tracker::hostMap(Env::peer()->address(), $peers);

        Observer::instance()->seeBroadcasts($hosts, MainChain::instance()->lastBlock());

        return true;
    }
}