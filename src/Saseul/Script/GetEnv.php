<?php

namespace Saseul\Script;

use Core\Script;
use Saseul\Data\Env;

class GetEnv extends Script
{
    public $_description = "Display all env information of the node. ";

    public function main()
    {
        $this->addOption('all', 'a', '', 'Display all information ');
        $this->addOption('node', 'n', '<option>', 'Display node information (Option: null|private_key|public_key|address) ');
        $this->addOption('peer', 'p', '<option>', 'Display peer information (Option: null|private_key|public_key|address) ');
        $this->addOption('miner', 'm', '', 'Display miner address ');
        $this->addOption('endpoint', 'e', '', 'Display endpoint ');

        $all = $this->_args['all'] ?? ($this->_args['a'] ?? null);
        $help = $this->_args['help'] ?? ($this->_args['h'] ?? null);
        $node = $this->_args['node'] ?? ($this->_args['n'] ?? null);
        $peer = $this->_args['peer'] ?? ($this->_args['p'] ?? null);
        $miner = $this->_args['miner'] ?? ($this->_args['m'] ?? null);
        $endpoint = $this->_args['endpoint'] ?? ($this->_args['e'] ?? null);

        Env::load();

        if (!is_null($help)) {
            $this->help();
        } else if (!is_null($node)) {
            switch ($node) {
                case 'private_key':
                    $this->print(Env::node()->privateKey());
                    break;
                case 'public_key':
                    $this->print(Env::node()->publicKey());
                    break;
                case 'address':
                    $this->print(Env::node()->address());
                    break;
                default:
                    $this->node();
                    $this->print('');
                    break;
            }
        } else if (!is_null($peer)) {
            switch ($peer) {
                case 'private_key':
                    $this->print(Env::peer()->privateKey());
                    break;
                case 'public_key':
                    $this->print(Env::peer()->publicKey());
                    break;
                case 'address':
                    $this->print(Env::peer()->address());
                    break;
                default:
                    $this->peer();
                    $this->print('');
                    break;
            }
        } else if (!is_null($miner)) {
            $this->print(Env::owner());
        } else if (!is_null($endpoint)) {
            $this->print(Env::endpoint());
        } else if (!is_null($all)) {
            $this->node();
            $this->peer();
            $this->miner();
            $this->endpoint();
            $this->print('');
        } else {
            $this->simple();
        }
    }

    public function simple()
    {
        $this->print(PHP_EOL. 'Usage: saseul-script GetEnv -- [args...] ');
        $this->print('  saseul-script GetEnv --help ');
        $this->print(PHP_EOL. 'Node: '. Env::node()->address());
        $this->print('Peer: '. Env::peer()->address());
        $this->print('Miner: '. Env::owner());

        if (!is_null(Env::endpoint()) && Env::endpoint() !== '') {
            $this->print('Endpoint: '. Env::endpoint());
        }

        $this->print('');
    }

    public function node()
    {
        $str = PHP_EOL. 'Node: ';
        $str.= PHP_EOL. '  Private key: '. Env::node()->privateKey();
        $str.= PHP_EOL. '  Public key: '. Env::node()->publicKey();
        $str.= PHP_EOL. '  Address: '. Env::node()->address();

        $this->print($str);
    }

    public function peer()
    {
        $str = PHP_EOL. 'Peer:';
        $str.= PHP_EOL. '  Private key: '. Env::peer()->privateKey();
        $str.= PHP_EOL. '  Public key: '. Env::peer()->publicKey();
        $str.= PHP_EOL. '  Address: '. Env::peer()->address();

        $this->print($str);
    }

    public function miner()
    {
        $this->print(PHP_EOL. 'Miner: '. Env::owner());
    }

    public function endpoint()
    {
        $this->print(PHP_EOL. 'Endpoint: '. Env::endpoint());
    }
}
