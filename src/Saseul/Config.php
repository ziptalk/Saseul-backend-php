<?php

namespace Saseul;

use Util\Hasher;
use Util\Signer;

class Config
{
    public const FULL_LEDGER = 'full';
    public const PARTIAL_LEDGER = 'partial';
    public const LEDGER_FILESIZE_LIMIT = 268435456;
    public const ZERO_ADDRESS = '00000000000000000000000000000000000000000000';

    public const MAIN_CHAIN_INTERVAL = 1000000;
    public const MAIN_CONSENSUS_PER = 0.6;

    public const RESOURCE_INTERVAL = 60000000;
    public const RESOURCE_CONFIRM_COUNT = 10;
    public const CONFIRM_INTERVAL = self::RESOURCE_INTERVAL * self::RESOURCE_CONFIRM_COUNT;
    public const VALIDATOR_COUNT = 9;

    public const DIFFICULTY_CHANGE_CYCLE = 1440;
    public const DEFAULT_DIFFICULTY = '100000';
    public const MINING_INTERVAL = 15000000;
    public const REFRESH_INTERVAL = 15000000;

    public const MAX_DIFFICULTY_WEIGHT = 4;
    public const MIN_DIFFICULTY_WEIGHT = 0.25;

    public const HASH_COUNT = '115792089237316195423570985008687907853269984665640564039457584007913129639936';

    public const BLOCK_TX_SIZE_LIMIT = 16777216;
    public const BLOCK_TX_COUNT_LIMIT = 2048;
    public const TX_SIZE_LIMIT = 1048576;
    public const STATUS_SIZE_LIMIT = 65536;

    public const RECEIPT_COUNT_LIMIT = 256;
    public const TIMESTAMP_ERROR_LIMIT = 5000000;

    public const EXA = '1000000000000000000';
    public const STANDARD_AMOUNT = '2000000000000000000000'; # 2000 * 10^18;
    public const CREDIT_AMOUNT = '60000000000000000000000000'; # 60000000 * 10^18;

    public const ROUND_TIMEOUT = 1;
    public const DATA_TIMEOUT = 2;

    public const MASTER_ADDR = '127.0.0.1';
    public const MASTER_PORT = 9933;
    public const DATA_POOL_ADDR = '127.0.0.1';
    public const DATA_POOL_PORT = 9934;

    public static $_environment = 'process';
    public static $_version = '';
    public static $_data = ROOT. DS. 'data';

    public static $_network = '';
    public static $_system_nonce = '';
    public static $_genesis_address = '';
    public static $_manager_addresses = [];

    public static $_ledger = 'partial';
    public static $_database = false;
    public static $_collect = true;
    public static $_consensus = true;
    public static $_mining = true;

    public static $_mysql_host = 'localhost';
    public static $_mysql_port = 3306;
    public static $_mysql_user = '';
    public static $_mysql_database = '';
    public static $_mysql_password = '';

    public static $_peers = [];

    public static function data(?string $data = null): string
    {
        return self::$_data = $data ?? self::$_data;
    }

    public static function env(): string
    {
        return self::data(). DS. 'env';
    }

    public static function peers(): string
    {
        return self::data(). DS. 'peers';
    }

    public static function knownHosts(): string
    {
        return self::data(). DS. 'known_hosts';
    }

    public static function chainInfo(): string
    {
        return self::data(). DS. 'chain_info';
    }

    public static function bunch(): string
    {
        return self::data(). DS. 'bunch';
    }

    public static function mainChain(): string
    {
        return self::data(). DS. 'main_chain';
    }

    public static function resourceChain(): string
    {
        return self::data(). DS. 'resource_chain';
    }

    public static function statusBundle(): string
    {
        return self::data(). DS. 'status_bundle';
    }

    public static function rootSpace(): string
    {
        return Hasher::hash(self::$_system_nonce);
    }

    public static function rootSpaceId(): string
    {
        return Hasher::spaceId(self::ZERO_ADDRESS, self::rootSpace());
    }

    public static function networkKey(): string
    {
        return Hasher::hash(self::$_network);
    }

    public static function networkAddress(): string
    {
        return Signer::address(self::networkKey());
    }

    public static function txCountHash(): string
    {
        return Hasher::statusHash(self::ZERO_ADDRESS, self::rootSpace(), 'transaction_count', self::ZERO_ADDRESS);
    }

    public static function calculatedHeightHash(): string
    {
        return Hasher::statusHash(self::ZERO_ADDRESS, self::rootSpace(), 'fixed_height', self::ZERO_ADDRESS);
    }

    public static function resourceHash(string $address): string
    {
        return Hasher::statusHash(self::ZERO_ADDRESS, self::rootSpace(), 'resource', $address);
    }

    public static function recycleResourceHash(): string
    {
        return Hasher::statusHash(self::ZERO_ADDRESS, self::rootSpace(), 'recycle_resource', self::ZERO_ADDRESS);
    }

    public static function contractPrefix(): string
    {
        return Hasher::statusPrefix(self::ZERO_ADDRESS, self::rootSpace(), 'contract');
    }

    public static function requestPrefix(): string
    {
        return Hasher::statusPrefix(self::ZERO_ADDRESS, self::rootSpace(), 'request');
    }
}
