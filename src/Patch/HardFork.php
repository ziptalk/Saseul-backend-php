<?php

namespace Patch;

use Saseul\Config;
use Saseul\Data\MainChain;
use Saseul\Model\MainBlock;
use Saseul\Model\ResourceBlock;

class HardFork
{
    public static function resourceCondition(ResourceBlock $block): bool
    {
        if (Config::$_network === "SASEUL PUBLIC NETWORK") {
            $main_block = MainChain::instance()->block($block->main_height);

            return $block->height <= 83500 && $main_block->height > 0;
        }

        return false;
    }

    public static function resourceConditionA(ResourceBlock $block): bool
    {
        if (Config::$_network === "SASEUL PUBLIC NETWORK") {
            // legacy
            return $block->height <= 625000;
        }

        return false;
    }

    public static function validators(MainBlock $block): bool
    {
        if (Config::$_network === "SASEUL PUBLIC NETWORK") {
            # 59779 ~ 59788: Hard fork;
            return $block->height >= 59779 && $block->height <= 59788;
        }

        return false;
    }

    public static function forkValidators(): array
    {
        return [ Config::$_genesis_address ];
//            return Config::$_manager_addresses;
    }

    public static function confirmedHeight(MainBlock $block): bool
    {
        if (Config::$_network === "SASEUL PUBLIC NETWORK") {
            # 40937 ~ 41032: Bug blocks;
            return $block->height >= 40937 && $block->height <= 41032;
        }

        return false;
    }

    public static function forkHeight(int $height): int
    {
        return $height - 65536;
    }

    public static function mainCondition(MainBlock $block): bool
    {
        if (Config::$_network === "SASEUL PUBLIC NETWORK") {
            return $block->height <= 150000;
        }

        return false;
    }
}