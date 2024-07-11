<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Config;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Data\Tracker;
use Saseul\Model\Method;
use Saseul\RPC\Code;
use Saseul\RPC\Factory;
use Saseul\RPC\Sender;
use Util\Clock;

class SendTransaction extends Script
{
    public $_description = "Execute the method of the smart contract to create a transaction and broadcast it. ";
    private $codes = [];

    public function main()
    {
        $this->addOption('cid', 'c', '<cid>', 'Enter the contract ID to call. ');
        $this->addOption('method', 'm', '<name>', 'Enter the name of the method to be called. ');
        $this->addOption('key', 'k', '<key>', 'Enter the private key to sign the request (Optional) ');
        $this->addOption('data', 'd', '<json>', 'Enter transaction data (JSON Format)');

        if (isset($this->_args['help']) || isset($this->_args['h'])) {
            $this->help();
            return;
        }

        $this->codes = Code::contracts()['methods'];

        $cid = $this->_args['cid'] ?? ($this->_args['c'] ?? Config::rootSpaceId());
        $method = $this->_args['method'] ?? ($this->_args['m'] ?? null);
        $private_key = $this->_args['key'] ?? ($this->_args['k'] ?? Env::node()->privateKey());
        $json = $this->_args['data'] ?? ($this->_args['d'] ?? null);

        if (is_null($method) || !isset($this->codes[$cid][$method])) {
            $this->methods($cid);
            return;
        }

        if (!is_null($json)) {
            $data = json_decode($json, true);

            if (is_null($data)) {
                $this->print(PHP_EOL. "Invalid json data format: $json". PHP_EOL);
            }
        } else {
            $data = $json;
        }

        if (is_null($data)) {
            $data = $this->data($cid, $method);
        }

        $peers = Tracker::getPeerHosts();
        shuffle($peers);

        $now = Clock::utime() + 1000000;
        $data['timestamp'] = $now;
        $transaction = Factory::cTransaction($cid, $method, $data, $private_key);
        $weight = Sender::weight(Factory::cTransaction($cid, $method, $data, $private_key));

        $this->print('');
        $this->print('Tx weight: '. $weight. PHP_EOL);

        $broadcast_count = (int) sqrt(count($peers)) + 3;
        $broadcast_count = min($broadcast_count, count($peers));
        $check = false;

        $this->print('Sending transaction ... ');

        for ($i = 0; $i < $broadcast_count; $i++) {
            $host = $peers[$i];

            $result = json_decode(Sender::tx($transaction, $host), true) ?? [];
            $code = $result['code'] ?? null;
            $msg = $result['msg'] ?? '';

            if (!is_null($code)) {
                if ($code === 200) {
                    $n = $i + 1;
                    $this->print("Success! Peer: $n, Tx hash: $transaction->hash");
                    $check = true;
                } else {
                    $this->print("Failed: $msg");
                }
            }
        }

        if ($check) {
            $this->checkTx($transaction->hash, $transaction->timestamp);
        }
    }

    public function checkTx(string $hash, int $timestamp)
    {
        $this->print('');

        for ($i = 0; $i < 10; $i++) {
            $last_block = MainChain::instance()->lastBlock();
            $tx = MainChain::instance()->transaction($hash);
            $exists = isset($tx['transaction']);
            $height = $last_block->height;
            $s_timestamp = $last_block->s_timestamp;

            if ($exists) {
                $this->print(PHP_EOL. "Block confirmed! ". PHP_EOL);
                $this->print($last_block->minimalObj());
                $this->print('');
                break;
            } else if ($s_timestamp >= $timestamp) {
                $this->print("Failed: The transaction could not be included in a block. height: $height / timestamp: $timestamp / s_timestamp: $s_timestamp");
                break;
            } else {
                $this->print("Checking transaction confirmation status... height: $height / timestamp: $timestamp / s_timestamp: $s_timestamp");
                sleep(3);
            }
        }
    }

    public function methods(string $cid): void
    {
        $methods = $this->codes[$cid] ?? [];

        $this->print(PHP_EOL. 'Usage: saseul-script SendTransaction --cid <cid> --method <method_name> -- [args...]');
        $this->print('  saseul-script SendTransaction --help'. PHP_EOL);

        foreach ($methods as $name => $method) {
            $this->print("- $name");
        }

        $this->print('');
    }

    public function data($cid, $name): array
    {
        $method = $this->codes[$cid][$name] ?? new Method();

        $this->print('Please enter JSON data in the following format: ');
        $this->print('{');

        foreach ($method->parameters() as $parameter) {
            $name = $parameter['name'] ?? '';
            $type = $parameter['type'] ?? '';
            $maxlength = $parameter['maxlength'] ?? 0;
            $requirements = $parameter['requirements'] ?? false;
            $requirements ? $optional = '' : $optional = '(Optional)';

            $this->print("  \"$name\":<$type, maxlength: $maxlength> $optional");
        }

        $this->print('}');
        $data = $this->ask('');
        $json_data = json_decode($data, true);

        if ($data === '') {
            return [];
        }

        if (is_null($json_data)) {
            $this->print('');
            $this->print('Invalid input value.');
            exit;
        }

        return $json_data;
    }
}