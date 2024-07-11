<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\Data\Tracker;
use Saseul\Data\Status;
use Saseul\ScriptTool;

class Reset extends Script
{
    public $_description = "Delete all data. ";

    use ScriptTool;

    function main()
    {
        $this->print('Are you sure you want to delete all the data? ');

        if (strtolower($this->ask('If you want to reset, please type "reset". ')) !== 'reset') {
            $this->print('The reset has been canceled. ');
            return;
        }

        $this->restart(function () {
            Tracker::reset();
            Chain::reset();
            MainChain::instance()->reset();
            ResourceChain::instance()->reset();
            Status::instance()->reset();

            $this->print('All data has been deleted. ');
        });
    }
}
