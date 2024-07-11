<?php

namespace Saseul\Script;

use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\DataSource\PoolClient;
use Core\Script;
use Util\Clock;
use Util\Parser;

class Info extends Script
{
    public $_description = "Display current status information of the node. ";

    public function main()
    {
        $confirmed_height = Chain::confirmedHeight(Clock::ufloortime());
        $miners = Chain::selectMiners($confirmed_height);
        $validators = Chain::selectValidators($confirmed_height);

        $this->print('');
        $this->print('[Info]');
        $this->print(Parser::alignedString($this->info()));
        $this->print('');
        $this->print('[Miners]');
        $this->print(Parser::alignedString($miners));
        $this->print('');
        $this->print('[Validators]');
        $this->print(Parser::alignedString($validators));
    }

    function info(): array
    {
        $last_block = MainChain::instance()->lastBlock()->minimalObj();
        $last_resource_block = ResourceChain::instance()->lastBlock()->minimalObj();
        $policy = PoolClient::instance()->getPolicy();

        if (is_array($policy)) {
            return [
                'is_running' => true,
                'version' => SASEUL_VERSION,
                'chain_maker_policy' => ($policy['chain_maker'] ?? false),
                'resource_miner_policy' => ($policy['resource_miner'] ?? false),
                'collector_policy' => ($policy['collector'] ?? false),
                'mining' => ($policy['mining'] ?? false),
                'last_block' => $last_block,
                'last_resource_block' => $last_resource_block,
            ];
        } else {
            return [
                'is_running' => false,
                'version' => SASEUL_VERSION,
                'last_block' => $last_block,
                'last_resource_block' => $last_resource_block,
            ];
        }
    }
}