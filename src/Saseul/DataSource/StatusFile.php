<?php

namespace Saseul\DataSource;

use Core\Logger;
use Saseul\Config;
use Saseul\Model\MainBlock;
use Util\File;
use Util\Filter;
use Util\Hasher;
use Util\Parser;

class StatusFile
{
    # indexes: $key => [$file_id, $seek, $length]
    # cached_indexes: $key => [$file_id, $seek, $length, $iseek]

    public $cached_universal_indexes = [];
    public $cached_local_indexes = [];
    public $tasks = [];

    public function touch(): void
    {
        clearstatcache();

        File::makeDirectory(Config::statusBundle());
        File::append($this->tempFile());
        File::append($this->infoFile());
        File::append($this->localFile());
        File::append($this->localBundle());
        File::append($this->localBundleIndex());
        File::append($this->universalBundleIndex());
    }

    public function init(): void
    {
        $this->touch();
    }

    public function reset(): void
    {
        File::delete(Config::statusBundle());
        $this->touch();
    }

    public function cache(): void
    {
        if (count($this->cached_local_indexes) === 0 && count($this->cached_universal_indexes) === 0) {
            Logger::log('Bundling: caching.. ');
            $this->touch();
            $this->commit();
            $this->cached_universal_indexes = Protocol::readStatusIndex($this->universalBundleIndex(), true);
            $this->cached_local_indexes = Protocol::readStatusIndex($this->localBundleIndex(), true);
        }
    }

    public function flush(): void
    {
        $this->cached_universal_indexes = [];
        $this->cached_local_indexes = [];
    }

    public function update(MainBlock $block): bool
    {
        clearstatcache();
        $local_updates = $block->local_updates;
        $universal_updates = $block->universal_updates;

        # locals;
        $indexes = $this->localIndexes(array_keys($local_updates));
        $indexes = $this->updateLocal($indexes, $local_updates);
        $update_local = $this->addLocalIndexes($indexes);

        # universals;
        $indexes = $this->universalIndexes(array_keys($universal_updates));
        $indexes = $this->updateUniversal($indexes, $universal_updates);
        $update_universal = $this->addUniversalIndexes($indexes);

        return $update_universal && $update_local;
    }

    public function write(MainBlock $block): bool
    {
        clearstatcache();

        # data;
        $this->writeUniversal($block->universal_updates);
        $this->writeLocal($block->local_updates);

        # height
        $this->tasks[] = [$this->infoFile(), 0, $block->height];

        # pre-commit;
        $this->writeTasks();

        # commit;
        $this->commit();

        return true;
    }

    public function bundleHeight(): int
    {
        return (int) File::read($this->infoFile());
    }

    public function updateUniversal(array $indexes, array $universal_updates): array
    {
        $null = pack('C', 0);
        $latest_file_id = $this->maxFileId('universals-');
        $latest_file = $this->universalFile($latest_file_id);

        File::append($latest_file);
        $_seek = filesize($latest_file);

        foreach ($universal_updates as $key => $update)
        {
            $key = Hasher::fillHash($key);
            $index = $indexes[$key] ?? [];
            $data = serialize($update['new']);
            $length = strlen($data);
            $_length = $index[2] ?? 0;

            if ($_length < $length) {
                # append new line;
                $file_id = $latest_file_id;
                $seek = $_seek;

                if (Config::LEDGER_FILESIZE_LIMIT < $seek + $length) {
                    $file_id = $this->fileId($file_id);
                    $seek = 0;
                    $_seek = 0;
                }

                $_seek = $_seek + $length;
            } else {
                # overwrite;
                $file_id = $index[0];
                $seek = $index[1];
                $length = $_length;
                $data = str_pad($data, $length, $null, STR_PAD_RIGHT);
            }

            $indexes[$key] = [$file_id, $seek, $length];
            File::write($this->universalFile($file_id), $seek, $data);
        }

        return $indexes;
    }

    public function updateLocal(array $indexes, array $local_updates): array
    {
        $null = pack('C', 0);
        $file_id = $this->fileId();
        $file = $this->localFile();

        File::append($file);
        $_seek = filesize($file);

        foreach ($local_updates as $key => $update)
        {
            $key = Hasher::fillHash($key);
            $index = $indexes[$key] ?? [];
            $data = serialize($update['new']);
            $length = strlen($data);
            $_length = $index[2] ?? 0;

            if ($_length < $length) {
                # append new line;
                $seek = $_seek;
                $_seek = $_seek + $length;
            } else {
                # overwrite;
                $seek = $index[1];
                $length = $_length;
                $data = str_pad($data, $length, $null, STR_PAD_RIGHT);
            }

            $indexes[$key] = [$file_id, $seek, $length];
            File::write($this->localFile(), $seek, $data);
        }

        return $indexes;
    }

    public function writeUniversal(array $universal_updates): void
    {
        $null = pack('C', 0);
        $latest_file_id = $this->maxFileId('ubundle-');
        $latest_file = $this->universalBundle($latest_file_id);
        $index_file = $this->universalBundleIndex();

        File::append($latest_file);
        $_seek = filesize($latest_file);
        $_iseek = filesize($index_file);

        foreach ($universal_updates as $key => $update)
        {
            $key = Hasher::fillHash($key);
            $index = $this->cached_universal_indexes[$key] ?? [];
            $data = serialize($update['new']);
            $length = strlen($data);
            $_length = $index[2] ?? 0;

            if ($_length < $length) {
                # append new line;
                $file_id = $latest_file_id;
                $seek = $_seek;
                $_seek = $_seek + $length;

                if (Config::LEDGER_FILESIZE_LIMIT < $seek + $length) {
                    $file_id = $this->fileId($file_id);
                    $seek = 0;
                    $_seek = 0;
                }

                if ($_length === 0) {
                    # new data;
                    $iseek = $_iseek;
                    $_iseek = $_iseek + Protocol::STATUS_HEAP_BYTES;
                } else {
                    # existing data;
                    $iseek = $index[3];
                }
            } else {
                # overwrite;
                $file_id = $index[0];
                $seek = $index[1];
                $iseek = $index[3];
                $length = $_length;
                $data = str_pad($data, $length, $null, STR_PAD_RIGHT);
            }

            $index = [$file_id, $seek, $length, $iseek];
            $index_data = $this->indexRaw($key, $file_id, $seek, $length);

            $this->cached_universal_indexes[$key] = $index;
            $this->tasks[] = [$this->universalBundle($file_id), $seek, $data];
            $this->tasks[] = [$this->universalBundleIndex(), $iseek, $index_data];
        }
    }

    public function writeLocal(array $local_updates): void
    {
        $null = pack('C', 0);
        $file_id = $this->fileId();
        $file = $this->localBundle();
        $index_file = $this->localBundleIndex();

        File::append($file);
        $_seek = filesize($file);
        $_iseek = filesize($index_file);

        foreach ($local_updates as $key => $update)
        {
            $key = Hasher::fillHash($key);
            $index = $this->cached_local_indexes[$key] ?? [];
            $data = serialize($update['new']);
            $length = strlen($data);
            $_length = $index[2] ?? 0;

            if ($_length < $length) {
                # append new line;
                $seek = $_seek;
                $_seek = $_seek + $length;

                if ($_length === 0) {
                    # new data;
                    $iseek = $_iseek;
                    $_iseek = $_iseek + Protocol::STATUS_HEAP_BYTES;
                } else {
                    # existing data;
                    $iseek = $index[3];
                }
            } else {
                # overwrite;
                $seek = $index[1];
                $iseek = $index[3];
                $length = $_length;
                $data = str_pad($data, $length, $null, STR_PAD_RIGHT);
            }

            $index = [$file_id, $seek, $length, $iseek];
            $index_data = $this->indexRaw($key, $file_id, $seek, $length);

            $this->cached_local_indexes[$key] = $index;
            $this->tasks[] = [$this->localBundle(), $seek, $data];
            $this->tasks[] = [$this->localBundleIndex(), $iseek, $index_data];
        }
    }

    public function writeTasks(): void
    {
        File::overwrite($this->tempFile(), serialize($this->tasks));
        $this->tasks = [];
    }

    public function commit(): void
    {
        $raw = File::read($this->tempFile());
        $tasks = unserialize($raw);

        if (is_array($tasks)) {
            foreach ($tasks as $item) {
                $file = $item[0];
                $seek = $item[1];
                $data = $item[2];

                if ($file === $this->infoFile()) {
                    File::overwrite($file, $data);
                } else {
                    File::write($file, $seek, $data);
                }
            }

            File::overwrite($this->tempFile());
        }
    }

    public function localStatuses(array $keys): array
    {
        $indexes = $this->localIndexes($keys);

        return $this->readLocalStatuses($indexes);
    }

    public function universalStatuses(array $keys): array
    {
        $indexes = $this->universalIndexes($keys);

        return $this->readUniversalStatuses($indexes);
    }

    public function searchLocalStatuses(string $prefix, int $page = 0, int $count = 50): array
    {
        $indexes = PoolClient::instance()->searchLocalIndexes($prefix, $page, $count);

        return $this->readLocalStatuses($indexes);
    }

    public function searchUniversalStatuses(string $prefix, int $page = 0, int $count = 50): array
    {
        $indexes = PoolClient::instance()->searchUniversalIndexes($prefix, $page, $count) ?? [];

        return $this->readUniversalStatuses($indexes);
    }

    public function countLocalStatuses(string $prefix): int
    {
        return PoolClient::instance()->countLocalIndexes($prefix);
    }

    public function countUniversalStatuses(string $prefix): int
    {
        return PoolClient::instance()->countUniversalIndexes($prefix);
    }

    public function localIndexes(array $keys = []): array
    {
        $result = [];

        while (count($keys) > 0) {
            $bucket = array_splice($keys, 0, 50);
            $indexes = PoolClient::instance()->localIndexes($bucket);

            foreach ($indexes as $key => $index) {
                $result[$key] = $index;
            }
        }

        return $result;
    }

    public function universalIndexes(array $keys = []): array
    {
        $result = [];

        while (count($keys) > 0) {
            $bucket = array_splice($keys, 0, 50);
            $indexes = PoolClient::instance()->universalIndexes($bucket);

            foreach ($indexes as $key => $index) {
                $result[$key] = $index;
            }
        }

        return $result;
    }

    public function addLocalIndexes(array $indexes = []): bool
    {
        while (count($indexes) > 0) {
            $bucket = array_splice($indexes, 0, 50);
            PoolClient::instance()->addLocalIndexes($bucket);
        }

        return true;
    }

    public function addUniversalIndexes(array $indexes = []): bool
    {
        while (count($indexes) > 0) {
            $bucket = array_splice($indexes, 0, 50);
            PoolClient::instance()->addUniversalIndexes($bucket);
        }

        return true;
    }

    public function readLocalStatuses(array $indexes = []): array
    {
        $result = [];

        foreach ($indexes as $key => $index) {
            if (count($index) === 3) {
                $seek = (int) $index[1];
                $length = (int) $index[2];
                $result[$key] = unserialize(File::readPart($this->localFile(), $seek, $length));
            } else {
                $result[$key] = null;
            }
        }

        return $result;
    }

    public function readUniversalStatuses(array $indexes): array
    {
        $result = [];

        foreach ($indexes as $key => $index) {
            if (count($index) === 3) {
                $file_id = (string) $index[0];
                $seek = (int) $index[1];
                $length = (int) $index[2];
                $result[$key] = unserialize(File::readPart($this->universalFile($file_id), $seek, $length));
            } else {
                $result[$key] = null;
            }
        }

        return $result;
    }

    public function indexRaw(string $key, string $file_id, int $seek, int $length): string
    {
        return Protocol::keyBin($key, Protocol::STATUS_KEY_BYTES).
            Protocol::fileIdBin($file_id).
            Parser::decbin($seek, Protocol::SEEK_BYTES).
            Parser::decbin($length, Protocol::LENGTH_BYTES);
    }

    public function fileId(?string $previous_id = null): string
    {
        if (is_string($previous_id) && Filter::isHex($previous_id)) {
            $previous = (int) Parser::hexdec($previous_id);

            return Parser::dechex($previous + 1, Protocol::DATA_ID_BYTES * 2);
        }

        return Parser::dechex(0, Protocol::DATA_ID_BYTES * 2);
    }

    public function maxFileId(string $prefix)
    {
        $files = File::grepFiles(Config::statusBundle(), Config::statusBundle(). DS. $prefix);

        if (count($files) > 0) {
            $files = str_replace(Config::statusBundle(). DS. $prefix, '', $files);

            return max($files);
        }

        return Parser::dechex(0, Protocol::DATA_ID_BYTES * 2);
    }

    public function copyBundles(): void
    {
        # local;
        $local_bundle = $this->localBundle();
        $local_file = $this->localFile();

        File::copy($local_bundle, $local_file);

        # universal;
        $universal_bundles = File::grepFiles(Config::statusBundle(), Config::statusBundle(). DS. 'ubundle-');
        $universal_files = File::grepFiles(Config::statusBundle(), Config::statusBundle(). DS. 'universals-');

        foreach ($universal_files as $file) {
            File::delete($file);
        }

        foreach ($universal_bundles as $bundle)
        {
            $from = $bundle;
            $to = str_replace(Config::statusBundle(). DS. 'ubundle-', Config::statusBundle(). DS. 'universals-', $bundle);

            File::copy($from, $to);
        }
    }

    public function infoFile(): string
    {
        return Config::statusBundle(). DS. 'info';
    }

    public function tempFile(): string
    {
        return Config::statusBundle(). DS. 'tmp';
    }

    public function localFile(): string
    {
        return Config::statusBundle(). DS. 'locals';
    }

    public function universalFile(string $file_id): string
    {
        return Config::statusBundle(). DS. 'universals-'. $file_id;
    }

    public function localBundle(): string
    {
        return Config::statusBundle(). DS. 'lbundle';
    }

    public function localBundleIndex(): string
    {
        return Config::statusBundle(). DS. 'lbundle_index';
    }

    public function universalBundle(string $file_id): string
    {
        return Config::statusBundle(). DS. 'ubundle-'. $file_id;
    }

    public function universalBundleIndex(): string
    {
        return Config::statusBundle(). DS. 'ubundle_index';
    }
}