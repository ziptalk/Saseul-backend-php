<?php

namespace Saseul\Script;

use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Core\Script;
use Saseul\Data\Status;
use Saseul\ScriptTool;

class RestoreBlock extends Script
{
    public $_description = "Delete some of the latest blocks and resynchronize. ";

    use ScriptTool;

    public function main()
    {
        $this->addOption('count', 'n', '<block count>', 'The number of blocks to be deleted ');

        $count = $this->_args['count'] ?? ($this->_args['n'] ?? null);

        is_null($count) ? $this->help() : $this->exec((int) $count);
    }

    public function exec(int $count)
    {
        $fixed_height = Chain::fixedHeight();
        $block_number = $fixed_height - $count;

        $this->restart(function () use ($block_number) {
            $restore_point = ResourceChain::instance()->block($block_number);
            $this->print("restore_point: $block_number");

            ResourceChain::instance()->remove($restore_point->height + 1);
            MainChain::instance()->remove($restore_point->main_height + 1);
            Chain::setFixedHeight($restore_point->height);
            Status::instance()->reset();

            $last_main = MainChain::instance()->lastHeight();
            $last_resource = ResourceChain::instance()->lastHeight();

            $this->print("last_resource: $last_resource");
            $this->print("last_main: $last_main");
        });
    }
}