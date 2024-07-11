<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\DataSource\ChainDB;
use Saseul\ScriptTool;

class ResetDB extends Script
{
    public $_description = "Deletes all data stored in the connected DB. (Only if using a DB) ";

    use ScriptTool;

    function main()
    {
        $this->print('Are you sure you want to delete all data in database? ');

        if (strtolower($this->ask('If you want to reset, please type "reset". ')) !== 'reset') {
            $this->print('The reset has been canceled. ');
            return;
        }

        $this->restart(function () {
            $chain_db = new ChainDB();
            $chain_db->init();
            $chain_db->reset();

            $this->print('All data in database has been deleted. ');
        });
    }
}
