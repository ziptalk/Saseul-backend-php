<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Data\Env;
use Saseul\ScriptTool;
use Saseul\Staff\ProcessManager;
use Util\Filter;
use Util\Signer;

class SetEnv extends Script
{
    public $_description = "Set the env information of the node. ";

    use ScriptTool;

    function main()
    {
        $this->addOption('all', 'a', '', 'Set all env information. ');
        $this->addOption('node', 'n', '<private_key>', 'Set node key ');
        $this->addOption('peer', 'p', '<private_key>', 'Set peer key ');
        $this->addOption('miner', 'm', '<address>', 'Set miner address ');
        $this->addOption('endpoint', 'e', '<endpoint>', 'Set endpoint ("": reset) ');

        $node = $this->_args['node'] ?? ($this->_args['n'] ?? null);
        $peer = $this->_args['peer'] ?? ($this->_args['p'] ?? null);
        $miner = $this->_args['miner'] ?? ($this->_args['m'] ?? null);
        $endpoint = $this->_args['endpoint'] ?? ($this->_args['e'] ?? null);
        $help = $this->_args['help'] ?? ($this->_args['h'] ?? null);

        $get_env = new GetEnv();
        Env::load();

        if (!is_null($help)) {
            $this->help();
            return;
        } else if (!is_null($node) || !is_null($peer) || !is_null($miner) || !is_null($endpoint)) {

            if (!is_null($node)) {
                if (Signer::keyValidity($node)) {
                    Env::node($node);
                } else {
                    $this->print('');
                    $this->print("Invalid node key: $node");
                    return;
                }
            }

            if (!is_null($peer)) {
                if (Signer::keyValidity($peer)) {
                    Env::peer($peer);
                } else {
                    $this->print('');
                    $this->print("Invalid peer key: $peer");
                    return;
                }
            }

            if (!is_null($miner)) {
                if (Signer::addressValidity($miner)) {
                    Env::owner($miner);
                } else {
                    $this->print('');
                    $this->print("Invalid address: $miner");
                    return;
                }
            }

            if (!is_null($endpoint)) {
                if ($endpoint === '') {
                    Env::deleteEndpoint();
                } else if (Filter::isPublicHost($endpoint)) {
                    Env::endpoint($endpoint);
                } else {
                    $this->print('');
                    $this->print("Invalid endpoint: $endpoint");
                    return;
                }
            }

            $this->restart(function () { Env::save(); });

            $this->print('');
            $this->print('Environment files has been changed. ');
        } else {
            $ask = $this->ask('Do you want to set up the env file? [y/n] ');

            if ($ask !== 'y') {
                return;
            }

            $ask = $this->ask('Do you want to make new random node account? [y/n] ');

            if ($ask === 'y') {
                Env::node(Signer::privateKey());
            } else {
                Env::node($this->askPrivateKey());
            }

            $ask = $this->ask('Do you want to make new random peer account? [y/n] ');

            if ($ask === 'y') {
                Env::peer(Signer::privateKey());
            } else {
                Env::peer($this->askPrivateKey());
            }

            $ask = $this->ask('Do you want the mining address to be the same as the address of the node? [y/n] ');

            if ($ask === 'y') {
                Env::owner(Env::node()->address());
            } else {
                Env::owner($this->askAddress());
            }

            $this->print('');
            $this->restart(function () { Env::save(); });

            $this->print('');
            $this->print('Environment files has been created. ');
        }

        usleep(300000);
        Env::load();
        $get_env->simple();
    }

    function askPrivateKey() {
        $private_key = $this->ask('Please enter your private key. ');

        if (Signer::keyValidity($private_key)) {
            return $private_key;
        } else {
            $this->print('Invalid private key. ');
            return $this->askPrivateKey();
        }
    }

    function askAddress() {
        $address = $this->ask('Please enter address. ');

        if (Signer::addressValidity($address)) {
            return $address;
        } else {
            $this->print('Invalid address. ');
            return $this->askAddress();
        }
    }
}
