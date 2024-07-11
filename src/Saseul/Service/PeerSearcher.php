<?php

namespace Saseul\Service;

use Core\Logger;
use Core\Service;
use Saseul\Config;
use Saseul\Data\Chain;
use Saseul\Data\Env;
use Saseul\Data\ResourceChain;
use Saseul\Data\Tracker;
use Saseul\DataSource\PoolClient;
use Saseul\Staff\ProcessManager;
use Util\Clock;
use Util\Filter;
use Util\Hasher;
use Util\Parser;
use Util\RestCall;
use Util\Signer;

class PeerSearcher extends Service
{
    protected $_iterate = 1000000;
    protected $_check_count = 32;

    public function __construct()
    {
        if (Config::$_environment === 'process') {
            if (ProcessManager::isRunning(ProcessManager::PEER_SEARCHER)) {
                Logger::log('Peer searcher process is already running. ');
                exit;
            }

            cli_set_process_title('saseul: peer_searcher');
            ProcessManager::save(ProcessManager::PEER_SEARCHER);
        }

        PoolClient::instance()->mode('rewind');
        RestCall::instance()->setTimeout(Config::ROUND_TIMEOUT);
    }

    public function __destruct()
    {
        if (ProcessManager::pid(ProcessManager::PEER_SEARCHER) === getmypid()) {
            Logger::log('Peer searcher process has been successfully removed. ');
            ProcessManager::delete(ProcessManager::PEER_SEARCHER);
        }
    }

    public function init()
    {
        $this->addRoutine([ $this, 'checkRequestedPeers' ], 5 * 1000000); # 5 sec;
        $this->addRoutine([ $this, 'checkPeers' ], 120 * 1000000); # 120 sec;
        $this->addRoutine([ $this, 'checkKnownHosts' ], 60 * 1000000); # 60 sec;
        $this->addRoutine([ $this, 'makeStatusBundle' ], 60 * 1000000); # 60 sec;

        $this->checkRequestedPeers();
        $this->checkPeers();
        $this->checkKnownHosts();
        $this->makeStatusBundle();

        Logger::log('Peer searcher process has started. ');
    }

    public function checkRequestedPeers() {
        # peer request --> known hosts;

        $requests = PoolClient::instance()->drainPeerRequests();
        $peer_hosts = Tracker::getPeerHosts();
        $known_hosts = Tracker::getKnownHosts();

        $bucket = [];

        foreach ($requests as $item) {
            if (!in_array($item, $peer_hosts) && Filter::isPublicHost($item)) {
                $bucket[] = Parser::endpoint($item);
            }
        }

        $known_hosts = array_unique(
            array_merge($known_hosts, $bucket)
        );

        Tracker::setKnownHosts($known_hosts);
    }

    public function checkPeers() {
        # peer --> peer (replace) && known hosts (merge);

        Logger::log('Check peers... ');

        $hosts = Tracker::getPeerHosts();
        $peers = [];
        $known_hosts = [];

        # requests by partitioning;
        while (count($hosts) > 0) {
            $part = array_splice($hosts, 0, $this->_check_count);
            $trackers = $this->seeTrackers($part, true);
            $peers = array_merge($peers, $trackers['peers']);
            $known_hosts = array_unique(
                array_merge($known_hosts, $trackers['known_hosts'])
            );
        }

        $peer_hosts = array_column($peers, 'host');
        $bucket = [];

        # duplicate removal;
        foreach ($known_hosts as $known_host) {
            if (!in_array($known_host, $peer_hosts) && Filter::isPublicHost($known_host)) {
                $bucket[] = Parser::endpoint($known_host);
            }
        }

        # known hosts (merge);
        $known_hosts = array_unique(
            array_merge($bucket, Tracker::getKnownHosts())
        );

        Tracker::setPeers($peers);
        Tracker::setKnownHosts($known_hosts);
    }

    public function checkKnownHosts() {
        # known_hosts --> peer (merge) && known hosts (replace);

        Logger::log('Check requested hosts... ');

        $hosts = Tracker::getKnownHosts();
        $peers = [];
        $known_hosts = [];

        # requests by partitioning;
        while (count($hosts) > 0) {
            $part = array_splice($hosts, 0, $this->_check_count);
            $trackers = $this->seeTrackers($part);
            $peers = array_merge($peers, $trackers['peers']);
            $known_hosts = array_unique(
                array_merge($known_hosts, $trackers['known_hosts'])
            );
        }

        $new_peers = Tracker::getPeers();
        $peer_hosts = array_column($new_peers, 'host');

        # peer (merge);
        foreach ($peers as $peer) {
            $host = $peer['host'] ?? '';
            $address = $peer['address'] ?? '';
            $exec_time = $peer['exec_time'] ?? 60;

            if (!in_array($host, $peer_hosts) && Filter::isPublicHost($host)) {
                $new_peers[] = [ 'host' => Parser::endpoint($host), 'address' => $address, 'exec_time' => $exec_time ];
            }
        }

        Tracker::setPeers($new_peers);

        # known hosts (replace);
        $peer_hosts = Tracker::getPeerHosts();
        $bucket = [];

        # duplicate removal;
        foreach ($known_hosts as $known_host) {
            if (!in_array($known_host, $peer_hosts) && Filter::isPublicHost($known_host)) {
                $bucket[] = Parser::endpoint($known_host);
            }
        }

        Tracker::setKnownHosts($bucket);
    }

    public function makeStatusBundle()
    {
        Logger::log('Bundling... ');
        Chain::bundling();
    }

    public function seeTrackers(array $hosts, bool $register = false): array
    {
        if (count($hosts) === 0) {
            return [];
        }

        $endpoint = Env::endpoint();
        $now = Clock::utime();
        $height = max(Chain::fixedHeight() - Config::RESOURCE_CONFIRM_COUNT, 0);
        $items = $this->searchRequestItems($hosts, $endpoint, $register, $height, $now);
        $items2 = $this->searchVersionItems($hosts, $endpoint);

        $height > 0 ? $phrase = ResourceChain::instance()->block($height)->blockhash : $phrase = Config::networkKey();
        $rs = RestCall::instance()->multiPOST($items);
        $rs2 = RestCall::instance()->multiPOST($items2);

        $peers = [];
        $known_hosts = [];
        $latests = [];

        foreach ($rs2 as $item) {
            $host = $item['host'];
            $result = json_decode($item['result'], true);
            $data = (array) ($result['data'] ?? []);
            $version = $data['version'] ?? '';

            if ($version >= '2.1.9.0') {
                $latests[] = $host;
            }
        }

        foreach ($rs as $item) {
            $host = $item['host'];
            $exec_time = $item['exec_time'] ?? 60;
            $result = json_decode($item['result'], true);

            if (!is_array($result)) {
                continue;
            }

            $data = (array) ($result['data'] ?? []);
            $node_data = (array) ($data['node'] ?? []);
            $peer_address = $this->peerAddress($node_data, $phrase, $now);

            if (is_null($peer_address) || !in_array($host, $latests)) {
                continue;
            }

            $peer_data = (array) ($data['peers'] ?? []);
            $peer_hosts = array_column($peer_data, 'host');

            $peers[] = [ 'host' => $host, 'address' => $peer_address, 'exec_time' => $exec_time ];
            $known_hosts = array_unique(
                array_merge($known_hosts, $peer_hosts)
            );
        }

        return [ 'peers' => $peers, 'known_hosts' => $known_hosts ];
    }

    private function searchRequestItems(array $hosts, ?string $endpoint, bool $register, int $height, int $utime): array
    {
        return array_map(
            function (string $host) use ($endpoint, $register, $height, $utime) {
                $item = [
                    'url' => "$host/peer",
                    'data' => [
                        'register' => $register,
                        'authentication' => true,
                        'height' => $height,
                        't' => $utime
                    ]
                ];

                is_null($endpoint) ?: $item['data']['host'] = $endpoint;

                return $item;
            }, $hosts);
    }

    private function searchVersionItems(array $hosts, ?string $endpoint): array
    {
        return array_map(
            function (string $host) use ($endpoint) {
                $item = [
                    'url' => "$host/info",
                    'data' => []
                ];

                is_null($endpoint) ?: $item['data']['host'] = $endpoint;

                return $item;
            }, $hosts);
    }

    private function peerAddress(array $node_data, string $phrase, int $now): ?string
    {
        $timestamp = @(int) ($node_data['timestamp'] ?? 0);
        $genesis_address = @(string) ($node_data['genesis_address'] ?? '');
        $public_key = @(string) ($node_data['public_key'] ?? '');
        $signature = @(string) ($node_data['signature'] ?? '');
        $string = Hasher::hash(Hasher::hextime($timestamp). $phrase);

        $time_validity = abs($now - $timestamp) < Config::TIMESTAMP_ERROR_LIMIT;
        $signature_validity = Signer::signatureValidity($string, $public_key, $signature);
        $genesis_validity = $genesis_address === Config::$_genesis_address;

        if ($time_validity && $signature_validity && $genesis_validity) {
            return Signer::address($public_key);
        }

        return null;
    }
}