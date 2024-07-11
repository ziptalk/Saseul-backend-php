<?php

namespace Saseul\Api;

use Saseul\Config;
use Saseul\Data\Env;
use Saseul\Data\ResourceChain;
use Saseul\Data\Tracker;
use Saseul\DataSource\PoolClient;
use Saseul\Rpc;
use Util\Clock;
use Util\Filter;
use Util\Hasher;
use Util\Parser;
use Util\Signer;

class Peer extends Rpc
{
    private $peers = [];
    private $known_hosts = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function main(): array
    {
        $this->checkRegister();
        $this->selectPeers();
        $this->readKnownHosts();

        $data = [];
        $data['peers'] = $this->peers;
        $data['known_hosts'] = array_values($this->known_hosts);

        $node = $this->node();

        if (!is_null($node)) {
            $data['node'] = $node;
        }

        return $data;
    }

    private function selectPeers(): void
    {
        $trackers = Tracker::getPeers();
        shuffle($trackers);

        foreach ($trackers as $tracker) {
            $host = $tracker['host'];
            $address = $tracker['address'];
            $prefix = substr($address, 0, 3);

            if (!isset($this->peers[$prefix])) {
                $this->peers[$prefix] = [ 'host' => $host, 'address' => $address ];
            } else {
                $this->known_hosts[] = $host;
            }
        }
    }

    private function readKnownHosts(): void
    {
        $this->known_hosts = array_unique(
            array_merge($this->known_hosts, Tracker::getKnownHosts())
        );
    }

    private function checkRegister()
    {
        $register = ((bool) ($_REQUEST['register'] ?? 0));

        if ($register === true) {
            $host = $_REQUEST['host'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

            if (Filter::isPublicHost($host)) {
                PoolClient::instance()->addPeerRequest(
                    Parser::endpoint($host)
                );
            }
        }
    }

    public function node(): ?array
    {
        $authentication = (bool) ($_REQUEST['authentication'] ?? 0);
        $height = (int) ($_REQUEST['height'] ?? 0);

        if ($authentication === true) {
            $phrase = Config::networkKey();

            if ($height > 0) {
                $resource_block = ResourceChain::instance()->block($height);

                if ($height === $resource_block->height) {
                    $phrase = $resource_block->blockhash;
                }
            }

            $timestamp = Clock::utime();
            $string = Hasher::hash(Hasher::hextime($timestamp). $phrase);

            return [
                'timestamp' => $timestamp,
                'genesis_address' => Config::$_genesis_address,
                'public_key' => Env::peer()->publicKey(),
                'signature' => Signer::signature($string, Env::peer()->privateKey()),
                'string' => $string,
                'a' => Signer::signatureValidity($string, Env::peer()->publicKey(), Signer::signature($string, Env::peer()->privateKey())),
            ];
        }

        return null;
    }
}