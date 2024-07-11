<?php

namespace Saseul\Script;

use Saseul\DataSource\PoolClient;
use Core\Script;

class StopMining extends Script
{
    public $_description = "Stop mining. ";

    public function main()
    {
        $this->print(PHP_EOL. 'Stopping mining ... ');

        $result = PoolClient::instance()->setPolicy('mining', false);

        if ($result) {
            $this->print('Mining has stopped successfully. ');
            $this->print('If you want to view the status, enter "saseul-script info" in the terminal.'. PHP_EOL);
        } else {
            $this->print('Failed to stop mining. ');
            $this->print('Please restart the SASEUL service. To restart, please enter "saseul-script restart" in the terminal.'. PHP_EOL);
        }
    }
}