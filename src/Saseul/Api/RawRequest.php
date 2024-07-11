<?php

namespace Saseul\Api;

use Core\Result;
use Saseul\Model\SignedRequest;
use Saseul\Rpc;
use Saseul\VM\Machine;

class RawRequest extends Rpc
{
    public function main()
    {
        $raw = file_get_contents('php://input');
        $item = json_decode($raw, true) ?? [];

        $err_msg = '';
        $request = new SignedRequest($item);
        $result = Machine::instance()->response($request, $err_msg);

        if (is_null($result)) {
            $this->fail(Result::FAIL, $err_msg);
        }

        return $result;
    }
}
