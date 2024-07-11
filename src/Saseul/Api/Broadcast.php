<?php

namespace Saseul\Api;

use Saseul\Config;
use Saseul\Data\Bunch;
use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Saseul\Model\Chunk;
use Saseul\Model\SignedTransaction;
use Saseul\Rpc;
use Util\Clock;

class Broadcast extends Rpc
{
    public function main(): ?array
    {
        # TODO: DDoS ?
        $chain_type = $_REQUEST['chain_type'] ?? 'main';

        if ($chain_type === 'resource') {
            return $this->resourceDatas();
        } elseif ($chain_type === 'all') {
            return $this->allDatas();
        }

        return $this->mainDatas();
    }

    public function allDatas(): array
    {
        $data = $this->mainDatas();
        $resource = $this->resourceDatas();

        $data['receipts'] = $resource['receipts'];

        return $data;
    }

    public function mainDatas(): array
    {
        $round_key = $_REQUEST['round_key'] ?? null;

        if (is_null($round_key)) {
            $round_block = MainChain::instance()->lastBlock();
            $round_key = $round_block->blockhash;
        } else {
            $round_block = MainChain::instance()->block($round_key);
        }

        $transactions = Bunch::listTx();
        $chunks = Bunch::listChunk($round_key);
        $hypotheses = Bunch::listHypothesis($round_key);

        $s_timestamps = array_column($chunks, 's_timestamp');

        if (count($s_timestamps) > 0) {
            $round_timestamp = max($s_timestamps);
        } else {
            $round_timestamp = Clock::ufloortime();
        }

        $confirmed_height = Chain::confirmedHeight($round_timestamp);
        $validators = Chain::selectValidators($confirmed_height);
        $txs = [];

        # chunks
        foreach ($chunks as $address => $chunk) {
            if (!in_array($address, $validators)) {
                unset($chunks[$address]);
                continue;
            }

            $chunk = new Chunk($chunk);

            foreach ($chunk->transactions as $thash) {
                if (isset($transactions[$thash])) {
                    $txs[$thash] = $transactions[$thash];
                    unset($transactions[$thash]);
                }
            }
        }

        # hypotheses
        foreach ($hypotheses as $address => $hypothesis) {
            if (!in_array($address, $validators)) {
                unset($hypotheses[$address]);
            }
        }

        # transactions;
        foreach ($transactions as $thash => $item) {
            if (count($txs) > Config::BLOCK_TX_COUNT_LIMIT) {
                break;
            }

            if (is_array($item)) {
                $transaction = new SignedTransaction($item);

                if ($transaction->timestamp > $round_block->s_timestamp) {
                    $txs[$thash] = $item;
                }
            }

            unset($transactions[$thash]);
        }

        return [
            'transactions' => $txs,
            'chunks' => $chunks,
            'hypotheses' => $hypotheses
        ];
    }

    public function resourceDatas(): array
    {
        $round_key = $_REQUEST['round_key'] ?? null;

        if (is_null($round_key)) {
            $round_key = MainChain::instance()->lastBlock()->blockhash;
        }

        return [
            'receipts' => Bunch::listReceipt($round_key)
        ];
    }
}