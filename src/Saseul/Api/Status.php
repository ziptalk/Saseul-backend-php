<?php

namespace Saseul\Api;

use Core\Api;
use Saseul\Data\Bunch;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Model\Receipt;
use Util\Hasher;
use Util\Signer;

class Status extends Api
{
    function main()
    {
        $signed_query = $_REQUEST['signed_query'] ?? '{}';
        $signed_query = (json_decode($signed_query, true) ?? []);

        $query = $signed_query['query'] ?? [];
        $public_key = @(string) $signed_query['public_key'] ?? '';
        $signature = @(string) $signed_query['signature'] ?? '';
        $round_key = @(string) $query['previous_blockhash'] ?? '';

        if (MainChain::instance()->lastBlock()->blockhash !== $round_key ||
            Signer::signatureValidity(Hasher::hash($query), $public_key, $signature) === false) {
            return null;
        }

        $receipt_header = [
            'previous_blockhash' => $round_key,
            'beneficiary' => Env::owner(),
            'signed_query' => $signed_query
        ];

        $receipt = new Receipt([
            'previous_blockhash' => $round_key,
            'beneficiary' => Env::owner(),
            'signed_query' => $signed_query,
            'public_key' => Env::peer()->publicKey(),
            'signature' => Signer::signature(Hasher::hash($receipt_header), Env::peer()->privateKey()),
        ]);

        Bunch::addReceipt($receipt);

        return null;
    }
}