<?php

namespace Saseul\Script;

use Saseul\Data\Tracker;
use Core\Script;
use Saseul\ScriptTool;

class ResetTracker extends Script
{
    public $_description = "Deletes all tracker information. ";

    use ScriptTool;

    function main()
    {
        $this->print('Are you sure you want to delete all tracker data? ');

        if (strtolower($this->ask('If you want to reset, please type "reset tracker". ')) !== 'reset tracker') {
            $this->print('The reset has been canceled. ');
            return null;
        }

        $this->restart(function () {
            Tracker::reset();
            $this->print('All tracker data has been deleted. ');
        });
    }
}