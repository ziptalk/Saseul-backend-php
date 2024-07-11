<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Staff\ProcessManager;

class Kill extends Script
{
    public $_description = "Kill all running processes. ";

    public function main()
    {
        $this->print(PHP_EOL. 'Killing all processes ... ');

        ProcessManager::kill(ProcessManager::MASTER);
        ProcessManager::kill(ProcessManager::RESOURCE_MINER);
        ProcessManager::kill(ProcessManager::CHAIN_MAKER);
        ProcessManager::kill(ProcessManager::COLLECTOR);
        ProcessManager::kill(ProcessManager::DATA_POOL);
        ProcessManager::kill(ProcessManager::PEER_SEARCHER);

        $this->print('All processes have been killed successfully. '. PHP_EOL);
    }
}