<?php

namespace Saseul\Data;

use Saseul\Config;
use Util\File;
use Util\Filter;
use Util\Parser;
use Util\Signer;

class Tracker
{
    public static function init()
    {
        if (count(self::getPeers()) === 0 && count(self::getKnownHosts()) === 0) {
            self::reset();
        }
    }

    public static function reset()
    {
        self::setPeers([]);
        self::setKnownHosts(Config::$_peers);
    }

    public static function setPeers(array $peers = [])
    {
        File::overwriteJson(Config::peers(), $peers);
    }

    public static function getPeers(): array
    {
        return File::readJson(Config::peers());
    }

    public static function getPeerHosts(): array
    {
        return array_column(self::getPeers(), 'host');
    }

    public static function addPeer(string $address, string $host)
    {
        if (Signer::addressValidity($address) && Filter::isPublicHost($host)) {
            $peers = self::getPeers();
            $peers[] = [
                'host' => Parser::endpoint($host),
                'address' => $address
            ];
            self::setPeers($peers);
        }
    }

    public static function setKnownHosts(array $known_hosts = [])
    {
        File::overwriteJson(Config::knownHosts(), array_values(array_unique($known_hosts)));
    }

    public static function getKnownHosts(): array
    {
        return File::readJson(Config::knownHosts());
    }

    public static function addKnownHosts(string $host): bool
    {
        if (Filter::isPublicHost($host)) {
            $known_hosts = self::getKnownHosts();
            $known_hosts[] = Parser::endpoint($host);
            self::setKnownHosts($known_hosts);
            return true;
        }

        return false;
    }

    public static function hostMap(string $address, array $peers): array
    {
        if (count($peers) === 0) {
            return [];
        }

        $addresses = array_column($peers, 'address');

        if (!in_array($address, $addresses)) {
            $peers[] = [ 'host' => 'localhost', 'address' => $address ];
        }

        array_multisort(array_column($peers, 'address'), $peers);
        $addresses = array_column($peers, 'address');
        $hosts = array_column($peers, 'host');

        $i = array_search($address, $addresses);
        $a = array_splice($addresses, $i);
        $b = array_splice($hosts, $i);
        $addresses = array_merge($a, $addresses);
        $hosts = array_merge($b, $hosts);

        for ($i = 2; $i < count($hosts); $i = $i * 2) {
            $items[] = $hosts[$i - 1];
            $items[] = $hosts[$i];
        }

        $items[] = $hosts[count($hosts) - 1];

        return array_values(array_unique($items));
    }
}