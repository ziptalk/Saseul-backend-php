<?php

namespace Saseul\RPC;

use Saseul\Model\SignedRequest;
use Saseul\Model\SignedTransaction;
use Saseul\VM\Machine;
use Util\RestCall;

class Sender
{
    public static function tx(SignedTransaction $tx, string $host = 'localhost')
    {
        return RestCall::instance()->post("$host/sendtransaction", [
            'transaction' => json_encode($tx->data),
            'public_key' => $tx->public_key,
            'signature' => $tx->signature
        ]);
    }

    public static function req(SignedRequest $req, string $host = 'localhost')
    {
        return RestCall::instance()->post("$host/request", [
            'request' => json_encode($req->data),
            'public_key' => $req->public_key,
            'signature' => $req->signature
        ]);
    }

    public static function localReq(SignedRequest $req)
    {
        $result = Machine::instance()->response($req, $err_msg);

        if (is_null($result)) {
            return $err_msg;
        }

        return $result;
    }

    public static function weight(SignedTransaction $tx)
    {
        return Machine::instance()->weight($tx);
    }

    public static function info(string $host = 'localhost')
    {
        return RestCall::instance()->post("$host/info");
    }

    public static function status(array $signed_query, string $host = 'localhost')
    {
        return RestCall::instance()->post("$host/status", ['signed_query' => json_encode($signed_query)]);
    }
}