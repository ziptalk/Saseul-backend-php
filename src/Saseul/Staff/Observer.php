<?php

namespace Saseul\Staff;

use Saseul\Config;
use Saseul\Data\Bunch;
use Saseul\Data\Chain;
use Saseul\Data\Env;
use Saseul\Model\Chunk;
use Saseul\Model\Hypothesis;
use Saseul\Model\MainBlock;
use Saseul\Model\Receipt;
use Saseul\Model\ResourceBlock;
use Saseul\Model\SignedTransaction;
use Saseul\RPC\Factory;
use Util\Clock;
use Util\RestCall;

class Observer
{
    public static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public $time_weight = 0;
    public $reset_count = 0;

    public function seeRounds(array $hosts, MainBlock $last_block): array
    {
        if (count($hosts) === 0) { return []; }

        RestCall::instance()->setTimeout(Config::ROUND_TIMEOUT + $this->time_weight);

        $data = http_build_query([
            'height' => $last_block->height + 1,
            'chain_type' => 'main',
            't' => Clock::utime()
        ]);

        $hosts = array_map(function ($host) use ($data) { return $host. '/round?'. $data; }, $hosts);
        $rounds = [];

        $rs = RestCall::instance()->multiGet($hosts);

        foreach ($rs as $item) {
            $host = $item['host'];
            $result = json_decode($item['result'], true);

            if (is_null($result)) {
                $this->addTimeWeight();
                $result = [];
            } else {
                $this->resetTimeWeight();
            }
            $data = (array) ($result['data'] ?? []);

            $block = (array) ($data['block'] ?? []);
            $sync_limit = @(int) ($data['sync_limit'] ?? 0);
            $block = new MainBlock($block);

            if ($block->height === $last_block->height + 1 &&
                $block->previous_blockhash === $last_block->blockhash &&
                $block->structureValidity()) {

                $rounds[$block->blockhash][] = [
                    'host' => $host,
                    'sync_limit' => $sync_limit,
                ];
            }
        }

        return $rounds;
    }

    public function seeMiningRounds(array $hosts, int $fixed_height): array
    {
        if (count($hosts) === 0) { return []; }

        RestCall::instance()->setTimeout(Config::ROUND_TIMEOUT + $this->time_weight);
        $data = http_build_query([
            'height' => $fixed_height,
            'chain_type' => 'resource',
            't' => Clock::utime()
        ]);

        $hosts = array_map(function ($host) use ($data) { return $host. '/round?'. $data; }, $hosts);
        $mining_rounds = [];

        $rs = RestCall::instance()->multiGet($hosts);

        foreach ($rs as $item) {
            $host = $item['host'];
            $result = json_decode($item['result'], true);

            if (is_null($result)) {
                $this->addTimeWeight();
                $result = [];
            } else {
                $this->resetTimeWeight();
            }
            $exec_time = $item['exec_time'];

            $data = (array) ($result['data'] ?? []);
            $block = (array) ($data['block'] ?? []);
            $sync_limit = @(int) ($data['sync_limit'] ?? 0);

            $block = new ResourceBlock($block);

            if ($block->height >= $fixed_height && $block->structureValidity()) {
                $mining_rounds[$block->blockhash][] = [
                    'host' => $host,
                    'sync_limit' => $sync_limit,
                    'exec_time' => $exec_time,
                ];
            }
        }

        return $mining_rounds;
    }

    public function seeBroadcasts(array $hosts, MainBlock $last_block): void
    {
        if (count($hosts) === 0) { return; }

        RestCall::instance()->setTimeout(Config::DATA_TIMEOUT + $this->time_weight);

        $now = Clock::utime();
        $round_key = $last_block->blockhash;
        $data = http_build_query([
            'chain_type' => 'main',
            'round_key' => $round_key,
            't' => $now
        ]);

        $hosts = array_map(function ($host) use ($data) { return $host. '/broadcast?'. $data; }, $hosts);
        $rs = RestCall::instance()->multiGet($hosts);

        $bunch_txs = Bunch::listTx();
        $bunch_chunks = Bunch::listChunk($round_key);
        $bunch_hypotheses = Bunch::listHypothesis($round_key);

        foreach ($rs as $item) {
            $result = json_decode($item['result'], true);

            if (is_null($result)) {
                $this->addTimeWeight();
                $result = [];
            } else {
                $this->resetTimeWeight();
            }
            $data = (array) ($result['data'] ?? []);

            $transactions = (array) ($data['transactions'] ?? []);
            $chunks = (array) ($data['chunks'] ?? []);
            $hypotheses = (array) ($data['hypotheses'] ?? []);

            foreach ($transactions as $key => $array) {
                if (isset($bunch_txs[$key]) || !is_array($array)) {
                    continue;
                }

                $transaction = new SignedTransaction($array);

                $validity = $transaction->validity() && $transaction->timestamp > $last_block->s_timestamp;

                if (!$validity) {
                    continue;
                }

                if (Bunch::addTx($transaction)) {
                    $bunch_txs[$transaction->hash] = $array;
                }
            }

            foreach ($chunks as $array) {
                if (!is_array($array)) {
                    continue;
                }

                $chunk = new Chunk($array);

                $confirmed_height = Chain::confirmedHeight($chunk->s_timestamp);
                $validators = Chain::selectValidators($confirmed_height);
                $signer = $chunk->signer();
                $validity = $chunk->validity() && in_array($signer, $validators) &&
                    $chunk->previous_blockhash === $round_key &&
                    $chunk->s_timestamp > $last_block->s_timestamp &&
                    $chunk->s_timestamp < $now + Config::TIMESTAMP_ERROR_LIMIT &&
                    $chunk->s_timestamp % Config::MAIN_CHAIN_INTERVAL === 0;

                if (!$validity) {
                    continue;
                }

                if (isset($bunch_chunks[$signer])) {
                    $old = new Chunk($bunch_chunks[$signer]);

                    if ($old->s_timestamp >= $chunk->s_timestamp) {
                        continue;
                    }
                }

                $tx_exists = true;

                foreach ($chunk->transactions as $thash) {
                    if (!is_string($thash) || !isset($transactions[$thash])) {
                        $tx_exists = false;
                        break;
                    }
                }

                if (!$tx_exists) {
                    continue;
                }

                if (Bunch::addChunk($chunk)) {
                    $bunch_chunks[$signer] = $array;
                }
            }

            foreach ($hypotheses as $array) {
                if (!is_array($array)) {
                    continue;
                }

                $hypothesis = new Hypothesis($array);

                $confirmed_height = Chain::confirmedHeight($hypothesis->s_timestamp);
                $validators = Chain::selectValidators($confirmed_height);
                $signer = $hypothesis->signer();
                $validity = $hypothesis->structureValidity() && $hypothesis->signatureValidity() &&
                    in_array($signer, $validators) &&
                    $hypothesis->previous_blockhash === $round_key &&
                    $hypothesis->s_timestamp > $last_block->s_timestamp &&
                    $hypothesis->s_timestamp < $now + Config::TIMESTAMP_ERROR_LIMIT &&
                    $hypothesis->s_timestamp % Config::MAIN_CHAIN_INTERVAL === 0;

                if (!$validity) {
                    continue;
                }

                if (isset($bunch_hypotheses[$signer])) {
                    $old = new Hypothesis($bunch_hypotheses[$signer]);

                    if ($old->s_timestamp >= $hypothesis->s_timestamp) {
                        continue;
                    }
                }

                $chunk_exists = true;

                foreach ($hypothesis->chunks as $address => $hash) {
                    if (!is_string($address) || !is_string($hash) || !isset($bunch_chunks[$address])) {
                        $chunk_exists = false;
                        break;
                    }
                }

                if (!$chunk_exists) {
                    continue;
                }

                if (Bunch::addHypothesis($hypothesis)) {
                    $bunch_hypotheses[$signer] = $array;
                }
            }
        }
    }

    public function seeResourceBroadcasts(array $hosts, ResourceBlock $last_block): void
    {
        if (count($hosts) === 0) { return; }

        RestCall::instance()->setTimeout(Config::ROUND_TIMEOUT + $this->time_weight);

        $round_key = $last_block->blockhash;
        $data = http_build_query([
            'chain_type' => 'resource',
            'round_key' => $round_key,
            't' => Clock::utime()
        ]);

        $hosts = array_map(function ($host) use ($data) { return $host. '/broadcast?'. $data; }, $hosts);
        $rs = RestCall::instance()->multiGet($hosts);
        $bunch_receipts = Bunch::listReceipt($round_key);

        foreach ($rs as $item) {
            $result = json_decode($item['result'], true);

            if (is_null($result)) {
                $this->addTimeWeight();
                $result = [];
            } else {
                $this->resetTimeWeight();
            }
            $data = (array) ($result['data'] ?? []);
            $receipts = (array) ($data['receipts'] ?? []);

            foreach ($receipts as $array) {
                if (!is_array($array)) {
                    continue;
                }

                $receipt = new Receipt($array);
                $signer = $receipt->signer();
                $validity = $receipt->validity() && $receipt->previous_blockhash === $round_key;

                if (!$validity || isset($bunch_receipts[$signer])) {
                    continue;
                }

                $bunch_receipts[$signer] = $array;
                Bunch::addReceipt($receipt);
            }
        }
    }

    public function seeBlocks(array $hosts, int $height): array
    {
        if (count($hosts) === 0) { return []; }

        RestCall::instance()->setTimeout(Config::DATA_TIMEOUT + $this->time_weight);

        $data = Factory::request(
            'GetBlocks', [ 'target' => $height, 'full' => true, 't' => Clock::utime() ],
            Env::peer()->privateKey()
        );

        $data = $data->json();
        $items = array_map(function ($host) use ($data) { return [ 'url' => "$host/rawrequest", 'data' => $data ]; }, $hosts);
        $rs = RestCall::instance()->multiPOST($items, [ 'Content-Type: application/json;' ]);

        $decisions = [];

        foreach ($rs as $item) {
            $host = $item['host'];
            $result = json_decode($item['result'], true);

            if (is_null($result)) {
                $this->addTimeWeight();
                $result = [];
            } else {
                $this->resetTimeWeight();
            }

            $blocks = (array) ($result['data'] ?? []);

            $decisions[] = [
                'host' => $host,
                'blocks' => $blocks
            ];
        }

        return $decisions;
    }

    public function seeResourceBlocks(array $hosts, int $height): array
    {
        if (count($hosts) === 0) { return []; }

        RestCall::instance()->setTimeout(Config::DATA_TIMEOUT + $this->time_weight);

        $data = Factory::request(
            'GetResourceBlocks', [ 'target' => $height, 'full' => true, 't' => Clock::utime() ],
            Env::peer()->privateKey()
        );

        $data = $data->json();
        $items = array_map(function ($host) use ($data) { return [ 'url' => "$host/rawrequest", 'data' => $data ]; }, $hosts);
        $rs = RestCall::instance()->multiPOST($items, [ 'Content-Type: application/json;' ]);

        $decisions = [];

        foreach ($rs as $item) {
            $host = $item['host'];
            $result = json_decode($item['result'], true);

            if (is_null($result)) {
                $this->addTimeWeight();
                $result = [];
            } else {
                $this->resetTimeWeight();
            }
            $blocks = (array) ($result['data'] ?? []);

            $decisions[] = [
                'host' => $host,
                'blocks' => $blocks
            ];
        }

        return $decisions;
    }

    public function addTimeWeight()
    {
        $this->time_weight = $this->time_weight + 1;

        if ($this->time_weight > 3) {
            $this->time_weight = 3;
        }
    }

    public function resetTimeWeight()
    {
        if ($this->reset_count > 50) {
            $this->reset_count = 0;
            $this->time_weight = 0;
        } else {
            $this->reset_count = $this->reset_count + 1;
        }
    }
}