<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Staff\MasterClient;
use Saseul\Staff\ProcessManager;

class Stop extends Script
{
    public $_description = "Stop the node. ";

    public function main()
    {
        $this->print(PHP_EOL. 'Stopping the master process ... ');

        for ($i = 0; $i < 3; $i++) {
            if (ProcessManager::isRunning(ProcessManager::MASTER)) {
                MasterClient::instance()->send('stop');
                usleep(500000);
            } else {
                $this->print('The master process has stopped successfully. '. PHP_EOL);
                return;
            }
        }

        $this->print('Failed to stop the master process. ');

        if (ProcessManager::isRunning(ProcessManager::MASTER)) {
            $kill = new Kill();
            $kill->main();
        }
    }
}