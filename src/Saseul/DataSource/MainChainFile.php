<?php

namespace Saseul\DataSource;

use Saseul\Config;
use Saseul\Model\MainBlock;

class MainChainFile extends ChainFile
{
    public function init()
    {
        $this->touch(Config::mainChain());
    }

    public function reset()
    {
        $this->resetData(Config::mainChain());
    }

    public function write(MainBlock $block): void
    {
        $directory = Config::mainChain();
        $this->writeData($directory, $block->height, $block->blockhash, $block->json());
    }

    public function lastHeight(): int
    {
        $directory = Config::mainChain();
        $index = $this->lastIndex($directory);

        return $index[0] ?? 0;
    }

    public function data($needle): string
    {
        $directory = Config::mainChain();
        $index = $this->index($directory, $needle);
        $height = $index[0] ?? 0;

        if ($height > 0) {
            return $this->readData($directory, $index);
        }

        return '';
    }

    public function search(string $hash): string
    {
        $directory = Config::mainChain();
        $index = $this->searchIndex($directory, $hash);
        $height = $index[0] ?? 0;

        if ($height > 0 && $height <= $this->lastHeight()) {
            return $this->readData($directory, $index);
        }

        return '';
    }

    public function remove(int $height): void
    {
        if ($height <= $this->lastHeight()) {
            $directory = Config::mainChain();
            $idx = $this->readIdx($directory, $height);
            $this->removeData($directory, $idx);
        }
    }
}