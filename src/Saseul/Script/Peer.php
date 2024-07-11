<?php

namespace Saseul\Script;

use Saseul\Data\Tracker;
use Core\Script;

class Peer extends Script
{
    public $_description = "Display peer information. ";

    function main()
    {
        $peers = Tracker::getPeers();
        $known_hosts = Tracker::getKnownHosts();

        array_multisort(array_column($peers, 'exec_time'), $peers);

        $peers = array_map(function ($item) {
            $host = $item['host'] ?? '';
            $address = $item['address'] ?? '';

            return "$address / $host ";
        }, $peers);

        $this->print('');
        $this->print('[Peer List]');
        $this->print($peers);
        $this->print('');
        $this->print('[Known hosts]');
        $this->print($known_hosts);
        $this->print('');
    }
}