<?php

namespace Saseul\Api;

use Core\Api;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\DataSource\PoolClient;
use Saseul\Staff\ProcessManager;

class Info extends Api
{
    function main(): array
    {
        $last_block = MainChain::instance()->lastBlock()->minimalObj();
        $last_resource_block = ResourceChain::instance()->lastBlock()->minimalObj();
        $policy = PoolClient::instance()->getPolicy();

        if (is_array($policy)) {
            return [
                'status' => 'is_running',
                'version' => SASEUL_VERSION,
                'chain_maker_policy' => ($policy['chain_maker'] ?? false),
                'resource_miner_policy' => ($policy['resource_miner'] ?? false),
                'collector_policy' => ($policy['collector'] ?? false),
                'mining' => ($policy['mining'] ?? false),
                'main_chain_waiting' => ($policy['main_chain_waiting'] ?? false),
                'resource_chain_waiting' => ($policy['resource_chain_waiting'] ?? false),
                'last_block' => $last_block,
                'last_resource_block' => $last_resource_block,
            ];
        } else {
            $status = 'stopped';

            if (ProcessManager::isRunning(ProcessManager::MASTER)) {
                $status = 'data initializing';
            }

            return [
                'status' => $status,
                'version' => SASEUL_VERSION,
                'last_block' => $last_block,
                'last_resource_block' => $last_resource_block,
            ];
        }
    }
}