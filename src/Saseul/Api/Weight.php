<?php

namespace Saseul\Api;

use Core\Result;
use Saseul\Model\SignedTransaction;
use Saseul\Rpc;
use Saseul\VM\Machine;

class Weight extends Rpc
{
    public function main(): string
    {
        $item = [];
        $item['transaction'] = json_decode(($_REQUEST['transaction'] ?? '{}'), true) ?? [];
        $item['public_key'] = $_REQUEST['public_key'] ?? '';
        $item['signature'] = $_REQUEST['signature'] ?? '';

        $tx = new SignedTransaction($item);
        $err_msg = '';
        $weight = Machine::instance()->weight($tx, $err_msg);

        if (is_null($weight)) {
            $this->fail(Result::FAIL, $err_msg);
        }

        return $weight;
    }
}
