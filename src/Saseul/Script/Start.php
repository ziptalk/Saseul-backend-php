<?php

namespace Saseul\Script;

use Core\Process;
use Core\Script;
use Saseul\Staff\ProcessManager;

class Start extends Script
{
    public $_description = "Start the node. ";

    public function main()
    {
        if (ProcessManager::isRunning(ProcessManager::MASTER)) {
            $this->print('Master process is already running. ');
            exit;
        }

        $str = PHP_EOL. "    ____      _     ____   _____  _   _  _               ____      _    ";
        $str.= PHP_EOL. "   / ___|    / \   / ___| | ____|| | | || |      __   __|___ \    / |   ";
        $str.= PHP_EOL. "   \___ \   / _ \  \___ \ |  _|  | | | || |      \ \ / /  __) |   | |   ";
        $str.= PHP_EOL. "    ___) | / ___ \  ___) || |___ | |_| || |___    \ V /  / __/  _ | |   ";
        $str.= PHP_EOL. "   |____/ /_/   \_\|____/ |_____| \___/ |_____|    \_/  |_____|(_)|_|   ". PHP_EOL;
        $str.= PHP_EOL. "SASEUL Engine v". SASEUL_VERSION. " ";
        $str.= PHP_EOL. "  Copyright, 2019-2023, All rights reserved by ArtiFriends Inc. ";
        $this->print($str. PHP_EOL);

        $this->print('Starting the master process ... ');
        Process::spawn(SERVICE_BIN, 'Master');

        for ($i = 0; $i < 10; $i++) {
            if (!ProcessManager::isRunning(ProcessManager::MASTER)) {
                usleep(300000);
            } else {
                $this->print('Master process has started successfully. ');
                $this->print('If you want to view the log, enter "saseul-script log" in the terminal. '. PHP_EOL);
                return;
            }
        }

        $this->print('Failed to start the master process. ');
    }
}