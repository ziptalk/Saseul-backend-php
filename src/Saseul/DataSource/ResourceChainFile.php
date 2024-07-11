<?php

namespace Saseul\DataSource;

use Saseul\Config;
use Saseul\Model\ResourceBlock;

class ResourceChainFile extends ChainFile
{
    public function init()
    {
        $this->touch(Config::resourceChain());
    }

    public function reset()
    {
        $this->resetData(Config::resourceChain());
    }

    public function write(ResourceBlock $block): void
    {
        $directory = Config::resourceChain();
        $this->writeData($directory, $block->height, $block->blockhash, $block->json());
    }

    public function lastHeight(): int
    {
        $directory = Config::resourceChain();
        $index = $this->lastIndex($directory);

        return $index[0] ?? 0;
    }

    public function data($needle): string
    {
        $directory = Config::resourceChain();
        $index = $this->index($directory, $needle);
        $height = $index[0] ?? 0;

        if ($height > 0) {
            return $this->readData($directory, $index);
        }

        return '';
    }

    public function search(string $hash): string
    {
        $directory = Config::resourceChain();
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
            $directory = Config::resourceChain();
            $idx = $this->readIdx($directory, $height);
            $this->removeData($directory, $idx);
        }
    }
}