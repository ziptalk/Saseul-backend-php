<?php

namespace Saseul\Data;

use Core\Logger;
use Saseul\Config;
use Saseul\Model\Account;
use Util\Encryptor;
use Util\File;
use Util\Filter;
use Util\Parser;
use Util\Signer;

class Env
{
    private static $pass = '';
    private static $node;
    private static $peer;
    private static $owner;
    private static $endpoint;

    public static function init(): void
    {
        # log
        Logger::init();

        # directories
        MainChain::instance();
        ResourceChain::instance();
        Status::instance();
        Bunch::init();
        Tracker::init();

        # env
        File::append(Config::env());
        File::append(Config::chainInfo());
    }

    public static function check(): bool
    {
        $log = is_file(Logger::$log_file);
        $main_chain = is_dir(Config::mainChain());
        $resource_chain = is_dir(Config::resourceChain());
        $status = is_dir(Config::statusBundle());
        $bunch = is_dir(Config::bunch()) && Bunch::test();
        $trackers = is_file(Config::peers()) && is_file(Config::knownHosts());
        $env = is_file(Config::env()) && self::exists();
        $info = is_file(Config::chainInfo());

        return ($log && $main_chain && $resource_chain && $status && $bunch && $trackers && $env && $info);
    }

    public static function exists(): bool
    {
        $data = File::readJson(Config::env());

        $encrypted_node_key = $data['node'] ?? '';
        $node_private_key = Encryptor::decode($encrypted_node_key, self::$pass) ?? '';

        return Signer::keyValidity($node_private_key);
    }

    public static function load(): void
    {
        if (!self::exists()) {
            return;
        }

        $data = File::readJson(Config::env());

        $encrypted_node_key = $data['node'] ?? '';
        $encrypted_peer_key = $data['peer'] ?? '';
        $node_private_key = Encryptor::decode($encrypted_node_key, self::$pass) ?? '';
        $peer_private_key = Encryptor::decode($encrypted_peer_key, self::$pass) ?? '';

        $owner = $data['owner'] ?? '';
        $endpoint = $data['endpoint'] ?? '';

        self::node($node_private_key);
        self::peer($peer_private_key);
        self::owner($owner);
        self::endpoint($endpoint);
    }

    public static function save(?array $data = null): void
    {
        if (is_null($data) && !is_null(self::$node) && !is_null(self::$peer)) {
            $node_private_key = Encryptor::encode(self::node()->privateKey(), self::$pass);
            $peer_private_key = Encryptor::encode(self::peer()->privateKey(), self::$pass);
            $owner = self::$owner ?? '';
            $endpoint = self::$endpoint ?? '';

            $data = [
                'node' => $node_private_key,
                'peer' => $peer_private_key,
                'owner' => $owner,
                'endpoint' => $endpoint,
            ];
        }

        File::overwriteJson(Config::env(), $data);
    }

    public static function node(?string $private_key = null): ?Account
    {
        if (!is_null($private_key) && Signer::keyValidity($private_key)) {
            self::$node = new Account($private_key);
        } elseif (is_null(self::$node)) {
            self::load();
        }

        return self::$node;
    }

    public static function peer(?string $private_key = null): ?Account
    {
        if (!is_null($private_key) && Signer::keyValidity($private_key)) {
            self::$peer = new Account($private_key);
        } elseif (is_null(self::$peer)) {
            self::load();
        }

        return self::$peer;
    }

    public static function owner(?string $address = null): ?string
    {
        if (!is_null($address) && Signer::addressValidity($address)) {
            self::$owner = $address;
        } elseif (is_null(self::$owner)) {
            self::load();
        }

        return self::$owner;
    }

    public static function endpoint(?string $endpoint = null): ?string
    {
        if (!is_null($endpoint) && Filter::isPublicHost($endpoint)) {
            self::$endpoint = Parser::endpoint($endpoint);
        }

        return self::$endpoint;
    }

    public static function deleteEndpoint(): void
    {
        self::$endpoint = null;
    }
}