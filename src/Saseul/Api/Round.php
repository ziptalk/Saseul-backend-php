<?php

namespace Saseul\Api;

use Core\Api;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Util\Clock;

class Round extends Api
{
    public function main(): ?array
    {
        $chain_type = $_REQUEST['chain_type'] ?? 'main';

        if ($chain_type === 'resource') {
            return $this->miningRound();
        } elseif ($chain_type === 'all') {
            return $this->round();
        }

        return $this->mainRound();
    }

    public function round(): array
    {
        $main_last = MainChain::instance()->lastHeight();
        $main_height = (int) ($_REQUEST['main'] ?? $main_last);
        $resource_last = ResourceChain::instance()->lastHeight();
        $resource_height = (int) ($_REQUEST['resource'] ?? $resource_last);

        $round['main'] = [
            'block' => MainChain::instance()->block($main_height)->minimalObj(),
            'sync_limit' => $main_last,
            'timestamp' => Clock::utime(),
        ];
        $round['resource'] = [
            'block' => ResourceChain::instance()->block($resource_height)->minimalObj(),
            'sync_limit' => $resource_last,
            'timestamp' => Clock::utime()
        ];

        return $round;
    }

    public function mainRound(): array
    {
        $last_height = MainChain::instance()->lastBlock()->height;
        $height = (int) ($_REQUEST['height'] ?? $last_height);

        $round = [];
        $round['block'] = MainChain::instance()->block($height)->minimalObj();
        $round['sync_limit'] = $last_height;
        $round['timestamp'] = Clock::utime();

        return $round;
    }

    public function miningRound(): array
    {
        $last_height = ResourceChain::instance()->lastBlock()->height;
        $height = (int) ($_REQUEST['height'] ?? $last_height);

        $round = [];
        $round['block'] = ResourceChain::instance()->block($height)->minimalObj();
        $round['sync_limit'] = $last_height;
        $round['timestamp'] = Clock::utime();

        return $round;
    }
}