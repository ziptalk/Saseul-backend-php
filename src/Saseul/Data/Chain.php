<?php

namespace Saseul\Data;

use Core\Logger;
use Saseul\Config;
use Saseul\Model\MainBlock;
use Saseul\Model\ResourceBlock;
use Util\Clock;
use Util\File;
use Util\Signer;

class Chain
{
    public static function reset()
    {
        self::setFixedHeight(0);
    }

    public static function fixedHeight(): int
    {
        return (int) File::read(Config::chainInfo());
    }

    public static function fixedPoint(int $timestamp): int
    {
        $last_height = ResourceChain::instance()->lastHeight();

        if ($last_height > 0) {
            $confirmed_height = self::confirmedHeight($timestamp);
            $fix_limit = max($last_height - 10, 1);

            return min($confirmed_height, $fix_limit);
        }

        return 0;
    }

    public static function updateFixedHeight(): void
    {
        $fixed_point = self::fixedPoint(Clock::ufloortime());
        $fixed_height = self::fixedHeight();

        if ($fixed_height < $fixed_point) {
            $target_block = ResourceChain::instance()->block($fixed_point);
            $main_block = MainChain::instance()->block($target_block->main_height);

            if ($target_block->main_blockhash === $main_block->blockhash) {
                self::setFixedHeight($fixed_point);
            }
        }
    }

    public static function setFixedHeight(int $height): void
    {
        File::overwrite(Config::chainInfo(), $height);
    }

    public static function fixedResourceBlock(): ResourceBlock
    {
        return ResourceChain::instance()->block(self::fixedHeight());
    }

    public static function fixedMainHeight(): int
    {
        $resource_block = self::fixedResourceBlock();

        return $resource_block->main_height;
    }

    public static function confirmedHeight(int $timestamp): int
    {
        return self::confirmedResourceBlock($timestamp)->height;
    }

    public static function confirmedResourceBlock(int $timestamp): ResourceBlock
    {
        $confirmed_timestamp = max($timestamp - Config::CONFIRM_INTERVAL, 0);

        return ResourceChain::instance()->beforeBlock($confirmed_timestamp);
    }

    public static function confirmedMainBlock(int $timestamp): MainBlock
    {
        $resource_block = self::confirmedResourceBlock($timestamp);

        return MainChain::instance()->block($resource_block->main_height);
    }

    public static function selectValidators(int $confirmed_height): array
    {
        if ($confirmed_height <= Config::RESOURCE_CONFIRM_COUNT) {
            return [ Config::$_genesis_address ];
//            return Config::$_manager_addresses;
        } else if ($confirmed_height === ResourceChain::instance()->lastBlock()->height) {
            return [];
        }

        $validators = [];
        $start_height = max($confirmed_height - Config::VALIDATOR_COUNT + 1, 1);

        for ($i = $start_height; $i <= $confirmed_height; $i++) {
            $block = ResourceChain::instance()->block($i);

            if (Signer::addressValidity($block->validator)) {
                $validators[] = $block->validator;
            }
        }

        return $validators;
    }

    public static function selectMiners(int $confirmed_height): array
    {
        if ($confirmed_height <= Config::RESOURCE_CONFIRM_COUNT) {
            return [ Config::$_genesis_address ];
//            return Config::$_manager_addresses;
        } else if ($confirmed_height === ResourceChain::instance()->lastBlock()->height) {
            return [];
        }

        $miners = [];
        $start_height = max($confirmed_height - Config::VALIDATOR_COUNT + 1, 1);

        for ($i = $start_height; $i <= $confirmed_height; $i++) {
            $block = ResourceChain::instance()->block($i);

            if (Signer::addressValidity($block->miner)) {
                $miners[] = $block->miner;
            }
        }

        return $miners;
    }

    public static function bundling(bool $once = true)
    {
        Status::instance()->cache();

        $fixed_height = self::fixedMainHeight();
        $bundle_height = Status::instance()->bundleHeight();

        for ($i = $bundle_height + 1; $i <= $fixed_height; $i++) {
            Status::instance()->write(MainChain::instance()->block($i));

            if ($i % 256 === 0 || $i === $fixed_height) {
                Logger::log("Commit Bundle: $i");

                if ($once) {
                    break;
                }
            }
        }
    }
}