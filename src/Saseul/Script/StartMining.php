<?php

namespace Saseul\Script;

use Saseul\DataSource\PoolClient;
use Core\Script;

class StartMining extends Script
{
    public $_description = "Start mining. ";

    public function main()
    {
        $this->print(PHP_EOL. 'Starting mining ... ');

        $result = PoolClient::instance()->setPolicy('mining', true);

        if ($result) {
            $this->print('Mining has started successfully. ');
            $this->print('If you want to view the status, enter "saseul-script info" in the terminal.'. PHP_EOL);
        } else {
            $this->print('Failed to start mining. ');
            $this->print('Please restart the SASEUL service. To restart, please enter "saseul-script restart" in the terminal.'. PHP_EOL);
        }
    }
}