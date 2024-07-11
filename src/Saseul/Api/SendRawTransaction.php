<?php

namespace Saseul\Api;

use Core\Result;
use Saseul\Config;
use Saseul\Data\Bunch;
use Saseul\Model\SignedTransaction;
use Saseul\Rpc;
use Saseul\VM\Machine;

class SendRawTransaction extends Rpc
{
    public function main(): string
    {
        $raw = file_get_contents('php://input');
        $item = json_decode($raw, true) ?? [];
        $tx = new SignedTransaction($item);
        $err_msg = '';

        $info = Bunch::infoTxs();
        $count = (int) ($info['count'] ?? 0);
        $size = (int) ($info['size'] ?? 0);

        if ($count > Config::BLOCK_TX_COUNT_LIMIT || $size + $tx->size() > Config::BLOCK_TX_SIZE_LIMIT) {
            return 'Too many transactions. ';
        }

        if (!Machine::instance()->txValidity($tx, $err_msg) || !Bunch::addTx($tx, $err_msg)) {
            $this->fail(Result::FAIL, $err_msg);
        }

        return "Transaction is added: $tx->hash";
    }
}
