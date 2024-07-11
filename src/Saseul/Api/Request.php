<?php

namespace Saseul\Api;

use Core\Result;
use Saseul\Model\SignedRequest;
use Saseul\Rpc;
use Saseul\VM\Machine;

class Request extends Rpc
{
    public function main()
    {
        $item = [];
        $item['request'] = json_decode(($_REQUEST['request'] ?? '{}'), true) ?? [];
        $item['public_key'] = $_REQUEST['public_key'] ?? '';
        $item['signature'] = $_REQUEST['signature'] ?? '';

        $err_msg = '';
        $request = new SignedRequest($item);
        $result = Machine::instance()->response($request, $err_msg);

        if (is_null($result)) {
            $this->fail(Result::FAIL, $err_msg);
        }

        return $result;
    }
}
