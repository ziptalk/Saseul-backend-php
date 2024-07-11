<?php

namespace Saseul\VM\Method;

use Saseul\Config;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\Data\Status;
use Saseul\Model\SignedTransaction;
use Saseul\VM\State;
use Util\Hasher;

trait ChainOperator
{
    # only request or resource chain
    public function get_block(array $vars = []): array
    {
        $target = $vars[0] ?? null;
        $full = (bool) ($vars[1] ?? false);

        if (is_numeric($target) || is_string($target)) {
            $block = MainChain::instance()->block($target);
        } else {
            $block = MainChain::instance()->lastBlock();
        }

        if ($full === true) {
            return $block->fullObj();
        } else {
            return $block->minimalObj();
        }
    }

    public function list_block(array $vars = []): array
    {
        $page = (int) ($vars[0] ?? 1);
        $count = (int) ($vars[1] ?? 20);
        $sort = (int) ($sort ?? -1);

        $blocks = [];

        if ($sort === 1) {
            $max = $page * $count;
            $min = max($max - $count, 1);

            for ($i = $min; $i <= $max; $i++) {
                $block = MainChain::instance()->block($i);
                $blocks[$block->blockhash] = $block->minimalObj();
            }
        } else {
            $min = MainChain::instance()->lastHeight() - ($page * $count);
            $max = $min + $count;

            for ($i = $max; $i > max($min, 0); $i = $i - 1) {
                $block = MainChain::instance()->block($i);
                $blocks[$block->blockhash] = $block->minimalObj();
            }
        }

        return $blocks;
    }

    public function block_count(array $vars = []): int
    {
        return MainChain::instance()->lastBlock()->height;
    }

    public function get_transaction(array $vars = []): array
    {
        $target = $vars[0] ?? null;

        if (is_string($target)) {
            return MainChain::instance()->transaction($target);
        } else {
            return MainChain::instance()->lastTransaction();
        }
    }

    public function list_transaction(array $vars = []): array
    {
        $count = (int) ($vars[0] ?? 20);
        $transactions = [];
        $last_height = MainChain::instance()->lastBlock()->height;

        for ($i = $last_height; $i > 0; $i--) {
            $txs = MainChain::instance()->block($i)->transactions;
            $tx_count = count($txs);

            for ($j = 0; $j < $tx_count; $j++) {
                $item = array_pop($txs);
                $tx = new SignedTransaction($item);
                $transactions[$tx->hash()] = $item;

                if (count($transactions) >= $count) {
                    return $transactions;
                }
            }
        }

        return $transactions;
    }

    public function transaction_count(array $vars = []): int
    {
        $data = Status::instance()->localStatuses([ Config::txCountHash() ]) ?? [0];
        $values = array_values($data);

        return ($values[0] ?? 0);
    }

    public function get_code(array $vars = [])
    {
        $type = @(string) ($vars[0] ?? 'contract');
        $target = @(string) ($vars[1] ?? '');
        $target = str_pad($target, Hasher::STATUS_KEY_SIZE, '0', STR_PAD_RIGHT);

        if (!in_array($type, ['contract', 'request'])) {
            return '';
        }

        if ($type === 'contract') {
            $hash = Config::contractPrefix(). $target;
        } else {
            $hash = Config::requestPrefix(). $target;
        }

        $result = Status::instance()->localStatuses([ $hash ]);

        return $result;
//        return @(string) $result[$hash];
    }

    public function list_code(array $vars = []): array
    {
        $page = (int) ($vars[0] ?? 1) - 1;
        $page = max($page, 0);
        $count = (int) ($vars[1] ?? 50);
        $count = min($count, 100);

        $contracts = Status::instance()->listLocalStatus(Config::contractPrefix(), $page, $count);
        $requests = Status::instance()->listLocalStatus(Config::requestPrefix(), $page, $count);

        foreach ($contracts as $key => $contract) {
            $suffix = substr($key, 64);
            $contracts[$suffix] = $contract;
            unset($contracts[$key]);
        }

        foreach ($requests as $key => $request) {
            $suffix = substr($key, 64);
            $requests[$suffix] = $request;
            unset($requests[$key]);
        }

        return [
            'contracts' => $contracts,
            'requests' => $requests,
        ];
    }

    public function code_count(array $vars = []): array
    {
        $contracts = Status::instance()->countLocalStatus(Config::contractPrefix());
        $requests = Status::instance()->countLocalStatus(Config::requestPrefix());

        return [
            'contracts' => $contracts,
            'requests' => $requests,
        ];
    }

    public function list_universal(array $vars = []): ?array
    {
        $attr = $vars[0] ?? null;
        $page = (int) ($vars[1] ?? 0);
        $page = max($page, 0);
        $count = (int) ($vars[2] ?? 50);
        $count = min($count, 100);

        if ($this->process === State::MAIN) {
            $status_prefix = Hasher::statusPrefix($this->code->writer(), $this->code->space(), $attr) ?? null;
        } elseif ($this->process === State::POST) {
            $status_prefix = Hasher::statusPrefix($this->post_process->writer(), $this->post_process->space(), $attr) ?? null;
        } else {
            $status_prefix = null;
        }

        if (is_null($status_prefix)) {
            return null;
        }

        return Status::instance()->listUniversalStatus($status_prefix, $page, $count);
    }

    public function get_resource_block(array $vars = []): ?array
    {
        $target = $vars[0] ?? null;
        $full = (bool) ($vars[1] ?? false);

        if (is_numeric($target) || is_string($target)) {
            $block = ResourceChain::instance()->block($target);
        } else {
            $block = ResourceChain::instance()->lastBlock();
        }

        if ($full === true) {
            return $block->fullObj();
        } else {
            return $block->minimalObj();
        }
    }

    public function list_resource_block(array $vars = []): array
    {
        $page = (int) ($vars[0] ?? 1);
        $count = (int) ($vars[1] ?? 20);
        $sort = (int) ($sort ?? -1);

        $blocks = [];

        if ($sort === 1) {
            $max = $page * $count;
            $min = max($max - $count, 1);

            for ($i = $min; $i <= $max; $i++) {
                $block = ResourceChain::instance()->block($i);
                $blocks[$block->blockhash] = $block->minimalObj();
            }
        } else {
            $min = ResourceChain::instance()->lastHeight() - ($page * $count);
            $max = $min + $count;

            for ($i = $max; $i > max($min, 0); $i = $i - 1) {
                $block = ResourceChain::instance()->block($i);
                $blocks[$block->blockhash] = $block->minimalObj();
            }
        }

        return $blocks;
    }

    public function resource_block_count(array $vars = []): int
    {
        return ResourceChain::instance()->lastBlock()->height;
    }

    public function get_blocks(array $vars = []): ?array
    {
        $target = (int) ($vars[0] ?? null);
        $full = (bool) ($vars[1] ?? false);
        $count = 0;
        $size = 0;
        $results = [];

        if ($target <= 0) {
            $target = 1;
        }

        for ($i = $target; $i < $target + 256; $i++) {
            $block = MainChain::instance()->block($i);

            if ($block->height === 0) {
                break;
            }

            if ($full === true) {
                $item = $block->fullObj();
            } else {
                $item = $block->minimalObj();
            }

            $tx_count = count($block->transactions);
            $length = strlen(json_encode($item));

            if ($count + $tx_count > Config::BLOCK_TX_COUNT_LIMIT ||
                $size + $length > Config::BLOCK_TX_SIZE_LIMIT) {
                break;
            }

            $results[$i] = $item;
            $count = $count + $tx_count;
            $size = $size + $length;

            if ($size > Config::TX_SIZE_LIMIT) {
                break;
            }
        }

        return $results;
    }

    public function get_resource_blocks(array $vars = []): ?array
    {
        $target = (int) ($vars[0] ?? null);
        $full = (bool) ($vars[1] ?? false);
        $count = 0;
        $size = 0;
        $results = [];

        $target = max($target, 1);

        for ($i = $target; $i < $target + 256; $i++) {
            $block = ResourceChain::instance()->block($i);
            $receipt_count = count($block->receipts);

            if ($block->height === 0) {
                break;
            }

            if ($full === true) {
                $item = $block->fullObj();
            } else {
                $item = $block->minimalObj();
            }

            $length = strlen(json_encode($item));

            if ($count + $receipt_count > Config::BLOCK_TX_COUNT_LIMIT ||
                $size + $length > Config::BLOCK_TX_SIZE_LIMIT) {
                break;
            }

            $results[$i] = $item;
            $count = $count + $receipt_count;
            $size = $size + $length;

            if ($size > Config::TX_SIZE_LIMIT) {
                break;
            }
        }

        return $results;
    }
}
