<?php

namespace Saseul\Api;

use Core\Result;
use Saseul\Config;
use Saseul\Data\MainChain;
use Saseul\DataSource\ChainDB;
use Saseul\DataSource\Database;
use Saseul\Rpc;
use Util\Clock;
use Util\Hasher;
use Util\Signer;

class Transaction extends Rpc
{
    public function main(): ?array
    {
        # If database === false, throw error;

        if (Config::$_database === false) {
            $this->fail(Result::FAIL, 'The database usage setting is set to false.');
        }

        # Prohibition of arbitrary/random calls.

        $now = Clock::utime();
        $timestamp = (int) ($_REQUEST['timestamp'] ?? 0);
        $public_key = $_REQUEST['public_key'] ?? '';
        $signature = $_REQUEST['signature'] ?? '';

        $time_validity = abs($now - $timestamp) < Config::TIMESTAMP_ERROR_LIMIT;
        $signature_validity = Signer::signatureValidity($timestamp, $public_key, $signature);

        if ($time_validity === false || $signature_validity === false) {
            $this->fail(Result::FAIL, 'Invalid request. ');
        }

        # read parameters.

        $data = $_REQUEST['data'] ?? '';

        switch ($data) {
            case "get":
                return $this->getTransaction();
            case "list":
            default:
                return $this->listTransaction();
            case "fullList":
                return $this->listTransaction(true);
        }
    }

    public function listTransaction(bool $full = false): ?array
    {
        $page = (int) ($_REQUEST['page'] ?? 0);
        $count = (int) ($_REQUEST['count'] ?? 50);

        $cid = $_REQUEST['cid'] ?? '';
        $type = $_REQUEST['type'] ?? '';
        $address = $_REQUEST['address'] ?? '';
        $from = $_REQUEST['from'] ?? '';
        $to = $_REQUEST['to'] ?? '';
        $timehash = $_REQUEST['timehash'] ?? '';

        $query = [];

        $if = Hasher::hashValidity($cid) ? $query['cid'] = $cid : null;
        $if = preg_match('/^[A-Za-z_0-9]+$/', $type) ? $query['type'] = Database::instance()->escape($type) : null;
        $if = Signer::addressValidity($address) ? $query['address'] = $address : null;
        $if = Signer::addressValidity($from) ? $query['from'] = $from : null;
        $if = Signer::addressValidity($to) ? $query['to'] = $to : null;
        $if = Hasher::timeHashValidity($timehash) ? $query['timehash'] = $timehash : null;

        $chain_db = new ChainDB();

        return $chain_db->listData($page, $count, $query, $full);
    }

    public function getTransaction(): ?array
    {
        $target = $_REQUEST['target'] ?? '05df2656734c9e326b71a0d256e39c01054f89f83027c7179fd9fe942a0d6270875c2d16f41732';

        return MainChain::instance()->transaction($target);
    }
}