<?php

namespace Saseul\Script;

use Core\Script;

class Restart extends Script
{
    public $_description = "Restart the node. ";

    public function main()
    {
        $start = new Start();
        $stop = new Stop();

        $stop->main();
        $start->main();
    }
}