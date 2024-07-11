<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Config;
use Saseul\Data\Env;
use Saseul\Model\Method;
use Saseul\RPC\Code;
use Saseul\RPC\Factory;
use Saseul\RPC\Sender;

class LocalRequest extends Script
{
    public $_description = "Execute the method of a smart contract based on data stored in the current node, and display the information ";

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

        $this->codes = Code::requests();
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

        $request = Factory::cRequest($cid, $method, $data, $private_key);
        $result = Sender::localReq($request);

        $this->print('');
        $this->print($result);
        $this->print('');
    }

    public function methods(string $cid): void
    {
        $methods = $this->codes[$cid] ?? [];

        $this->print(PHP_EOL. 'Usage: saseul-script LocalRequest --cid <cid> --method <method_name> -- [args...]');
        $this->print('  saseul-script LocalRequest --help'. PHP_EOL);

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