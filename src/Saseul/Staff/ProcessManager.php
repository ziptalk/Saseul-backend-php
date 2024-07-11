<?php

namespace Saseul\Staff;

use Core\Process;
use Saseul\Config;
use Util\File;

class ProcessManager
{
    public const MASTER = 'master';
    public const CHAIN_MAKER = 'maker';
    public const RESOURCE_MINER = 'miner';
    public const COLLECTOR = 'collector';
    public const PEER_SEARCHER = 'peer_searcher';
    public const DATA_POOL = 'data_pool';

    public static function exists(string $name): bool
    {
        clearstatcache();

        return file_exists(self::file($name));
    }

    public static function file(string $name): string
    {
        return Config::data(). DS. "$name.pid";
    }

    public static function save(string $pid): void
    {
        File::overwrite(self::file($pid), getmypid());
    }

    public static function delete(string $pid): void
    {
        clearstatcache();

        File::delete(self::file($pid));
    }

    public static function pid(string $name): int
    {
        return (int) File::read(self::file($name));
    }

    public static function isRunning(string $name): bool
    {
        $pid = self::pid($name);

        if ($pid <= 0) {
            return false;
        }

        return Process::isRunning($pid);
    }

    public static function kill(string $name): bool
    {
        if (!self::isRunning($name)) {
            return false;
        }

        $pid = self::pid($name);
        self::delete($name);
        Process::destroy($pid);
        return true;
    }
}