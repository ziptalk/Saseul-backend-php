<?php

namespace Saseul\Script;

use Saseul\Data\Tracker;
use Core\Script;

class AddTracker extends Script
{
    public $_description = 'Adding a tracker to the peer-to-peer search algorithm. ';

    public function main()
    {
        $this->addOption('peer', 'p', '<host>', 'The peer you want to register on the tracker. ');

        $peer = $this->_args['peer'] ?? ($this->_args['p'] ?? null);

        is_null($peer) ? $this->help() : $this->exec($peer);
    }

    public function exec(string $peer) {
        if (Tracker::addKnownHosts($peer)) {
            $this->print(PHP_EOL. "Tracker has been added: $peer". PHP_EOL);
        } else {
            $this->print(PHP_EOL. "Invalid peer: $peer ". PHP_EOL);
        }
    }
}