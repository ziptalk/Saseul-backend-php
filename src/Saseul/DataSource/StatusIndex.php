<?php

namespace Saseul\DataSource;

use Core\Logger;
use Saseul\Data\Chain;
use Saseul\Data\MainChain;
use Util\Hasher;

class StatusIndex
{
    public $local_indexes = [];
    public $universal_indexes = [];

    public function load(): void
    {
        # bundling;
        $status_file = new StatusFile();
        $status_file->cache();

        $fixed_height = Chain::fixedMainHeight();
        $bundle_height = $status_file->bundleHeight();

        for ($i = $bundle_height + 1; $i <= $fixed_height; $i++) {
            $block = MainChain::instance()->block($i);
            $status_file->write($block);

            if ($i % 256 === 0 || $i === $fixed_height) {
                Logger::log("Commit Bundle: Bundling.. $i");
            }
        }

        $status_file->flush();

        # update indexes;
        $status_file->copyBundles();

        # read;
        $local_indexes = Protocol::readStatusIndex($status_file->localBundleIndex());
        $universal_indexes = Protocol::readStatusIndex($status_file->universalBundleIndex());

        # updates;
        $last_height = MainChain::instance()->lastHeight();
        $bundle_height = $status_file->bundleHeight();

        Logger::log("Bundle Height: $bundle_height");
        Logger::log("Last Main Block Height: $last_height");

        for ($i = $bundle_height + 1; $i <= $last_height; $i++) {
            $block = MainChain::instance()->block($i);
            $local_indexes = $status_file->updateLocal($local_indexes, $block->local_updates);
            $universal_indexes = $status_file->updateUniversal($universal_indexes, $block->universal_updates);

            if ($i % 256 === 0 || $i === $last_height) {
                Logger::log("Update Status Datas... Height: $i");
            }
        }

        # cache;
        $this->addLocalIndexes($local_indexes);
        $this->addUniversalIndexes($universal_indexes);
    }

    public function localIndexes($keys): array
    {
        if (!is_array($keys)) {
            return [];
        }

        $indexes = [];

        foreach ($keys as $key) {
            $key = Hasher::fillHash($key);
            $split = Protocol::splitKey($key);
            $prefix = $split[0];
            $suffix = $split[1];

            if (isset($this->local_indexes[$prefix][$suffix])) {
                $indexes[$key] = $this->local_indexes[$prefix][$suffix];
            } else {
                $indexes[$key] = [];
            }
        }

        return $indexes;
    }

    public function universalIndexes($keys): array
    {
        if (!is_array($keys)) {
            return [];
        }

        $indexes = [];

        foreach ($keys as $key) {
            $key = Hasher::fillHash($key);
            $split = Protocol::splitKey($key);
            $prefix = $split[0];
            $suffix = $split[1];

            if (isset($this->universal_indexes[$prefix][$suffix])) {
                $indexes[$key] = $this->universal_indexes[$prefix][$suffix];
            } else {
                $indexes[$key] = [];
            }
        }

        return $indexes;
    }

    public function addLocalIndexes($indexes): bool
    {
        if (!is_array($indexes)) {
            return false;
        }

        foreach ($indexes as $key => $index) {
            $key = Hasher::fillHash($key);
            $split = $this->split($key);
            $prefix = $split[0];
            $suffix = $split[1];
            $this->local_indexes[$prefix][$suffix] = $index;
        }

        return true;
    }

    public function addUniversalIndexes($indexes): bool
    {
        if (!is_array($indexes)) {
            return false;
        }

        foreach ($indexes as $key => $index) {
            $key = Hasher::fillHash($key);
            $split = Protocol::splitKey($key);
            $prefix = $split[0];
            $suffix = $split[1];
            $this->universal_indexes[$prefix][$suffix] = $index;
        }

        return true;
    }

    public function searchLocalIndexes($item): array
    {
        if (!is_array($item)) {
            return [];
        }

        $indexes = [];
        $prefix = $item[0] ?? '';

        if (isset($this->local_indexes[$prefix])) {
            $page = $item[1] ?? 0;
            $count = $item[2] ?? 50;
            $offset = $page * $count;
            $slice = array_slice($this->local_indexes[$prefix], $offset, $count);

            foreach ($slice as $suffix => $index) {
                $key = $prefix. $suffix;
                $indexes[$key] = $index;
            }
        }

        return $indexes;
    }

    public function searchUniversalIndexes($item): array
    {
        if (!is_array($item)) {
            return [];
        }

        $indexes = [];
        $prefix = $item[0] ?? '';

        if (isset($this->universal_indexes[$prefix])) {
            $page = $item[1] ?? 0;
            $count = $item[2] ?? 50;
            $offset = $page * $count;
            $slice = array_slice($this->universal_indexes[$prefix], $offset, $count);

            foreach ($slice as $suffix => $index) {
                $key = $prefix. $suffix;
                $indexes[$key] = $index;
            }
        }

        return $indexes;
    }

    public function countUniversalIndexes($prefix): int
    {
        if (isset($this->local_indexes[$prefix])) {
            return count($this->local_indexes[$prefix]);
        }

        return 0;
    }

    public function countLocalIndexes($prefix): int
    {
        if (isset($this->local_indexes[$prefix])) {
            return count($this->local_indexes[$prefix]);
        }

        return 0;
    }

    public function split($key): array
    {
        $prefix = substr($key, 0, Hasher::STATUS_PREFIX_SIZE);
        $suffix = substr($key, Hasher::STATUS_PREFIX_SIZE);

        return [$prefix, $suffix];
    }
}