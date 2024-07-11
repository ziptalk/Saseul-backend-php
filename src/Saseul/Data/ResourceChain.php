<?php

namespace Saseul\Data;

use Core\Logger;
use Saseul\Config;
use Saseul\DataSource\ResourceChainFile;
use Saseul\Model\ResourceBlock;
use Util\Hasher;
use Util\Math;

class ResourceChain
{
    public static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public $source;

    public function __construct()
    {
//        $this->source = new ResourceChainDB();
        $this->source = new ResourceChainFile();

        $this->source->init();
    }

    public function reset()
    {
        $this->source->reset();
    }

    public function block($needle): ResourceBlock
    {
        $data = $this->source->data($needle);
        $data = json_decode($data, true) ?? [];

        return new ResourceBlock($data);
    }

    public function remove(int $height = 0)
    {
        Logger::log("removing resource chain... height >= $height");
        $this->source->remove($height);
    }

    public function write(ResourceBlock $block): bool
    {
        $last_block = $this->lastBlock();

        if ($block->height === $last_block->height + 1 && $block->previous_blockhash === $last_block->blockhash) {
            $this->source->write($block);
            return true;
        }

        return false;
    }

    public function forceWrite(ResourceBlock $block): void
    {
        $this->source->write($block);
    }

    public function receipt(string $hash): array
    {
        $data = $this->source->search($hash);
        $data = json_decode($data, true) ?? [];

        $block = new ResourceBlock($data);

        return $block->receipts[$hash] ?? [];
    }

    public function lastHeight(): int
    {
        return $this->source->lastHeight();
    }

    public function lastBlock(): ResourceBlock
    {
        $last_height = $this->lastHeight();

        return $this->block($last_height);
    }

    public function lastReceipt(): array
    {
        $block = $this->lastBlock();

        if ($block->height === 0) {
            return [];
        }

        return array_pop($block->receipts);
    }

    public function beforeBlock(int $timestamp): ResourceBlock
    {
        $data = $this->source->search(Hasher::hextime($timestamp));
        $data = json_decode($data, true) ?? [];

        $block = new ResourceBlock($data);

        if ($timestamp <= $block->timestamp) {
            return $this->block(max($block->height - 1, 0));
        }

        return $block;
    }

    public function difficulty(int $height)
    {
        if ($height < (Config::DIFFICULTY_CHANGE_CYCLE + 1)) {
            return Config::DEFAULT_DIFFICULTY;
        } elseif ($height % Config::DIFFICULTY_CHANGE_CYCLE !== 1) {
            return $this->block($height - 1)->difficulty;
        }

        $end_block = $this->block($height - 1);
        $start_block = $this->block($height - Config::DIFFICULTY_CHANGE_CYCLE - 1);
        $last_difficulty = $end_block->difficulty;

        $gap = $end_block->timestamp - $start_block->timestamp;
        $standard_gap = Config::RESOURCE_INTERVAL * Config::DIFFICULTY_CHANGE_CYCLE;

        $weight = Math::div($standard_gap, $gap, 3) ?? 0;
        $weight = ($weight > Config::MAX_DIFFICULTY_WEIGHT) ? Config::MAX_DIFFICULTY_WEIGHT : $weight;
        $weight = ($weight < Config::MIN_DIFFICULTY_WEIGHT) ? Config::MIN_DIFFICULTY_WEIGHT : $weight;

        $difficulty = Math::mul($last_difficulty, $weight, 0);

        if (Math::lte($difficulty, '1')) {
            $difficulty = '1';
        }

        return $difficulty;
    }
}