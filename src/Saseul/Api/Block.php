<?php

namespace Saseul\Api;

use Core\Api;
use Saseul\Config;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;

class Block extends Api
{
    public function main(): ?array
    {
        $chain_type = $_REQUEST['chain_type'] ?? 'all';

        if ($chain_type === 'main') {
            return $this->mainBlocks();
        } elseif ($chain_type === 'resource') {
            return $this->resourceBlocks();
        }

        return $this->allBlocks();
    }

    public function allBlocks(): array
    {
        $last_height = ResourceChain::instance()->lastHeight();
        $height = (int) ($_REQUEST['height'] ?? $last_height);

        $length = 0;
        $mains = [];
        $resources = [];

        for ($i = $height; $i < $height + 256; $i++) {
            $resource_block = ResourceChain::instance()->block($i);
            $previous_block = ResourceChain::instance()->block($i - 1);
            $size = strlen($resource_block->json());

            if ($resource_block->height === 0 || $length + $size > Config::BLOCK_TX_SIZE_LIMIT * 1.2) {
                return [
                    'main' => $mains,
                    'resources' => $resources,
                ];
            }

            $resources[$i] = $resource_block->fullObj();
            $length = $length + $size;

            for ($j = $previous_block->main_height + 1; $j <= $resource_block->main_height; $j++) {
                $block = MainChain::instance()->block($j);
                $size = strlen($block->json());

                if ($block->height === 0 || $length + $size > Config::BLOCK_TX_SIZE_LIMIT * 1.2) {
                    return [
                        'main' => $mains,
                        'resources' => $resources,
                    ];
                }

                $mains[$j] = $block->fullObj();
                $length = $length + $size;
            }
        }

        return [
            'main' => $mains,
            'resources' => $resources,
        ];
    }

    public function resourceBlocks(): array
    {
        $last_height = ResourceChain::instance()->lastHeight();
        $height = (int) ($_REQUEST['height'] ?? $last_height);

        $length = 0;
        $resources = [];

        for ($i = $height; $i < $height + 256; $i++) {
            $resource_block = ResourceChain::instance()->block($i);
            $size = strlen($resource_block->json());

            if ($resource_block->height === 0 || $length + $size > Config::BLOCK_TX_SIZE_LIMIT * 1.2) {
                return $resources;
            }

            $resources[$i] = $resource_block->fullObj();
            $length = $length + $size;
        }

        return $resources;
    }

    public function mainBlocks(): array
    {
        $last_height = MainChain::instance()->lastHeight();
        $height = (int) ($_REQUEST['height'] ?? $last_height);

        $length = 0;
        $mains = [];

        for ($i = $height; $i < $height + 256; $i++) {
            $block = MainChain::instance()->block($i);
            $size = strlen($block->json());

            if ($block->height === 0 || $length + $size > Config::BLOCK_TX_SIZE_LIMIT * 1.2) {
                return $mains;
            }

            $mains[$i] = $block->fullObj();
            $length = $length + $size;
        }

        return $mains;
    }
}