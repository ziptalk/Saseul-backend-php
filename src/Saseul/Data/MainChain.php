<?php

namespace Saseul\Data;

use Core\Logger;
use Saseul\Config;
use Saseul\DataSource\ChainDB;
use Saseul\DataSource\MainChainFile;
use Saseul\Model\MainBlock;

class MainChain
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
    public $db;

    public function __construct()
    {
        $this->source = new MainChainFile();
        $this->db = new ChainDB();

        $this->source->init();

        if (Config::$_database) {
            $this->db->init();
        }
    }

    public function makeDB()
    {
        if ($this->db->ready()) {
            $last_height = $this->lastHeight();
            $last_db_height = $this->db->lastHeight();

            for ($i = $last_db_height + 1; $i < $last_height; $i++) {
                $block = $this->block($i);
                $this->db->write($block);
            }
        }
    }

    public function reset()
    {
        $this->source->reset();
        $this->db->reset();
    }

    public function block($needle): MainBlock
    {
        $data = $this->source->data($needle);
        $data = json_decode($data, true) ?? [];

        return new MainBlock($data);
    }

    public function remove(int $height = 0)
    {
        Logger::log("removing main chain... height >= $height");
        $this->source->remove($height);
        $this->db->remove($height);
    }

    public function write(MainBlock $block): bool
    {
        $last_block = $this->lastBlock();

        if ($block->height === $last_block->height + 1 && $block->previous_blockhash === $last_block->blockhash) {

            $this->source->write($block);
            $this->db->write($block);

            Status::instance()->update($block);

            return true;
        }

        return false;
    }

    public function forceWrite(MainBlock $block): void
    {
        $this->source->write($block);
        $this->db->write($block);
    }

    public function transaction(string $hash): array
    {
        $data = $this->source->search($hash);
        $data = json_decode($data, true) ?? [];

        $block = new MainBlock($data);

        return $block->transactions[$hash] ?? [];
    }

    public function lastHeight(): int
    {
        return $this->source->lastHeight();
    }

    public function lastBlock(): MainBlock
    {
        $last_height = $this->lastHeight();

        return $this->block($last_height);
    }

    public function lastTransaction(): array
    {
        $block = $this->lastBlock();

        if ($block->height === 0) {
            return [];
        }

        return array_pop($block->transactions);
    }
}