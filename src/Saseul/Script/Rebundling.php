<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Data\Status;
use Saseul\ScriptTool;

class Rebundling extends Script
{
    public $_description = "Recomputes the status data based on block information. ";

    use ScriptTool;

    function main()
    {
        $this->restart(function () {
            $this->print("Status data has been reset.");
            Status::instance()->reset();
        });
    }
}
