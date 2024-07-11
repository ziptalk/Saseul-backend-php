<?php

namespace Saseul\RPC;

use Saseul\Model\SignedRequest;
use Saseul\Model\SignedTransaction;
use Util\Clock;
use Util\Signer;

class Factory
{
    public static function transaction(string $type, array $vars = [], ?string $private_key = null): SignedTransaction
    {
        if (is_null($private_key)) {
            $private_key = Signer::privateKey();
        }

        $transaction = self::data($type, $private_key);

        foreach ($vars as $key => $value) {
            $transaction[$key] = $value;
        }

        $signed_tx = new SignedTransaction([
            'transaction' => $transaction,
            'public_key' => Signer::publicKey($private_key),
        ]);

        $signed_tx->signature = Signer::signature($signed_tx->hash(), $private_key);

        return $signed_tx;
    }

    public static function request(string $type, array $vars = [], ?string $private_key = null): SignedRequest
    {
        if (is_null($private_key)) {
            $private_key = Signer::privateKey();
        }

        $request = self::data($type, $private_key);

        foreach ($vars as $key => $value) {
            $request[$key] = $value;
        }

        $signed_req = new SignedRequest([
            'request' => $request,
            'public_key' => Signer::publicKey($private_key),
        ]);

        $signed_req->signature = Signer::signature($signed_req->hash(), $private_key);

        return $signed_req;
    }

    public static function data(string $type, string $private_key, ?string $cid = null): array
    {
        $data = [
            'type' => $type,
            'timestamp' => Clock::utime(),
            'from' => Signer::address(Signer::publicKey($private_key)),
        ];

        if (!is_null($cid)) {
            $data['cid'] = $cid;
        }

        return $data;
    }

    public static function cTransaction(string $cid, string $type, array $vars = [], ?string $private_key = null): SignedTransaction
    {
        if (is_null($private_key)) {
            $private_key = Signer::privateKey();
        }

        $transaction = self::data($type, $private_key, $cid);

        foreach ($vars as $key => $value) {
            $transaction[$key] = $value;
        }

        $signed_tx = new SignedTransaction([
            'transaction' => $transaction,
            'public_key' => Signer::publicKey($private_key),
        ]);

        $signed_tx->signature = Signer::signature($signed_tx->hash(), $private_key);

        return $signed_tx;
    }

    public static function cRequest(string $cid, string $type, array $vars = [], ?string $private_key = null): SignedRequest
    {
        if (is_null($private_key)) {
            $private_key = Signer::privateKey();
        }

        $request = self::data($type, $private_key, $cid);

        foreach ($vars as $key => $value) {
            $request[$key] = $value;
        }

        $signed_req = new SignedRequest([
            'request' => $request,
            'public_key' => Signer::publicKey($private_key),
        ]);

        $signed_req->signature = Signer::signature($signed_req->hash(), $private_key);

        return $signed_req;
    }
}