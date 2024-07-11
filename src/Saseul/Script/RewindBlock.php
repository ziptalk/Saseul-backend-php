<?php

namespace Saseul\Script;

use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Core\Script;
use Saseul\ScriptTool;

class RewindBlock extends Script
{
    public $_description = "Delete unfinalized resource blocks and resynchronize. ";
    use ScriptTool;

    public function main()
    {
        $this->restart(function () {
            $fixed_height = Chain::fixedHeight();
            $restore_point = ResourceChain::instance()->block($fixed_height);
            $this->print("restore_point: $fixed_height");

            ResourceChain::instance()->remove($restore_point->height + 1);
            MainChain::instance()->remove($restore_point->main_height + 1);

            $last_main = MainChain::instance()->lastHeight();
            $last_resource = ResourceChain::instance()->lastHeight();

            $this->print("last_resource: $last_resource");
            $this->print("last_main: $last_main");
            $this->print('');
        });
    }
}