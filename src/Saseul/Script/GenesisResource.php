<?php

namespace Saseul\Script;

use Saseul\Data\Bunch;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\Model\ResourceBlock;
use Core\Script;
use Util\Clock;
use Util\Hasher;

class GenesisResource extends Script
{
    public $_description = 'Switch the generated network to PoW ';

    public function main(): void
    {
        $last_height = ResourceChain::instance()->lastHeight();

        if ($last_height > 0) {
            $this->print(PHP_EOL. 'There is already a resource block exists.'. PHP_EOL);
            return;
        }

        $last_main_block = MainChain::instance()->lastBlock();

        $expected_block = new ResourceBlock([
            'height' => 1,
            'blockhash' => '',
            'previous_blockhash' => '',
            'nonce' => '',
            'timestamp' => 0,
            'difficulty' => ResourceChain::instance()->difficulty(1),
            'main_height' => $last_main_block->height,
            'main_blockhash' => $last_main_block->blockhash,
            'validator' => Env::node()->address(),
            'miner' => Env::owner(),
            'receipts' => [],
        ]);

        do {
            $expected_block->timestamp = Clock::utime();
            $expected_block->nonce = $this->nonce();

        } while ($expected_block->nonceValidity() === false);

        $expected_block->blockhash = $expected_block->blockhash();

        if (!$this->commit($expected_block)) {
            return;
        }

        $this->print($expected_block->fullObj());
    }

    public function commit(ResourceBlock $resource_block): bool
    {
        # write;
        if (!ResourceChain::instance()->write($resource_block)) {
            $this->print('Resource block writing failed. height: '. $resource_block->height);
            return false;
        }

        # send;
        Bunch::removeReceipt($resource_block->main_blockhash);
        return true;
    }

    public function nonce(): string
    {
        return bin2hex(random_bytes(Hasher::HASH_BYTES));
    }
}
