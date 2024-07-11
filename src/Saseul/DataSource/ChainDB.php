<?php

namespace Saseul\DataSource;

use Core\Logger;
use Saseul\Config;
use Saseul\Model\MainBlock;
use Saseul\Model\SignedTransaction;
use Util\Hasher;

class ChainDB
{
    protected $ready = false;

    public function ready(): bool
    {
        return $this->ready;
    }

    public function init(): void
    {
        if (!Database::instance()->isConnect()) {
            Logger::log('Failed to connect to the database, will not use the chain database. ');
            return;
        }

        # init;
        $this->createDataIdDB();
        $this->createDataDB(Protocol::dataId());
        $this->ready = true;
    }

    public function reset(): void
    {
        if (!$this->ready) {
            return;
        }

        # drop all;
        $this->dropDatas();
        $this->dropDataIdDB();

        # create db;
        $this->init();
    }

    public function write(MainBlock $block): void
    {
        if (!$this->ready) {
            return;
        }

        # last id
        $data_id = $this->lastDataId();
        $data_table = $this->dataTable($data_id);
        $db_name = Database::instance()->databaseName();

        # check table size
        $rs = Database::instance()->exec("SELECT (data_length + index_length) `size` 
            FROM information_schema.tables WHERE `table_schema` = '$db_name' AND `table_name` = '$data_table'; ");

        if (!is_null($rs) && $row = $rs->fetch_assoc()) {
            $size = $row['size'] ?? 0;
        } else {
            $size = 0;
        }

        # check data size
        $length = strlen($block->json());

        if (Config::LEDGER_FILESIZE_LIMIT < $size + $length) {
            # new table;
            $data_id = Protocol::dataId($data_id);
            $size = 0;

            $this->dropDataDB($data_id);
            $this->createDataDB($data_id);
        }

        # write data;
        $this->addData($data_id, $block);
        $this->updateDataId($data_id, $block->height, $block->blockhash);
    }

    public function remove(int $height): void
    {
        if (!$this->ready) {
            return;
        }

        $data_id = $this->dataId($height);
        $data_table = $this->dataTable($data_id);

        # remove id data;
//        $this->dropDatas($data_id);
        $this->deleteData($data_id, $height);

        $rs = Database::instance()->exec("SELECT MAX(`blockhash`) `blockhash` FROM `$data_table`; ");
        $blockhash = '';

        if (!is_null($rs) && $row = $rs->fetch_assoc()) {
            $blockhash = $row['blockhash'] ?? '';
        }

        $this->updateDataId($data_id, $height - 1, $blockhash);
        $this->deleteDataId($height);
    }

    public function lastHeight(): int
    {
        if (!$this->ready) {
            return 0;
        }

        $rs = Database::instance()->exec("SELECT MAX(`height`) `height` FROM `data_id`; ");
        $height = 0;

        if (!is_null($rs) && $row = $rs->fetch_assoc()) {
            $height = $row['height'] ?? 0;
        }

        return $height;
    }

    public function lastDataId(): string
    {
        $rs = Database::instance()->exec("SELECT MAX(`idx`) `idx` FROM `data_id` ");
        $data_id = Protocol::dataId();

        if (!is_null($rs) && $row = $rs->fetch_assoc()) {
            $data_id = $row['idx'] ?? Protocol::dataId();
        }

        return $data_id;
    }

    public function dataId($needle): string
    {
        if (is_int($needle)) {
            # height
            $sql = "SELECT MIN(`idx`) `idx` FROM `data_id` WHERE `height` >= $needle; ";
        } else {
            $needle = substr($needle, 0, Hasher::HEX_TIME_SIZE);
            $needle = str_pad($needle, Hasher::TIME_HASH_SIZE, '0', STR_PAD_RIGHT);
            $sql = "SELECT MAX(`idx`) `idx` FROM `data_id` WHERE `hash` >= '$needle'; ";
        }

        $rs = Database::instance()->exec($sql);
        $data_id = Protocol::dataId();

        if (!is_null($rs) && $row = $rs->fetch_assoc()) {
            $data_id = $row['idx'] ?? Protocol::dataId();
        }

        return $data_id;
    }

    public function dropDatas($data_id = null) {
        for ($i = Protocol::dataId($data_id); $i <= $this->lastDataId(); $i = Protocol::dataId($i)) {
            $this->dropDataDB($i);
        }

        if (!is_null($data_id)) {
            Database::instance()->exec("DELETE FROM `data_id` WHERE `data_id` > '$data_id' ");
        }
    }

    public function dataTable(string $data_id): string
    {
        return 'data_'. $data_id;
    }

    public function dropDataIdDB(): void
    {
        Database::instance()->exec("DROP TABLE IF EXISTS `data_id`; ");
    }

    public function dropDataDB(string $data_id): void
    {
        Database::instance()->exec("DROP TABLE IF EXISTS `{$this->dataTable($data_id)}`; ");
    }

    public function createDataIdDB(): void
    {
        Database::instance()->exec("CREATE TABLE IF NOT EXISTS `data_id` (
              `idx` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
              `height` int(11) NOT NULL,
              `hash` varchar(78) COLLATE utf8mb4_unicode_ci NOT NULL,
              PRIMARY KEY (`idx`),
              UNIQUE KEY `height` (`height`),
              UNIQUE KEY `hash` (`hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; ");
    }

    public function createDataDB(string $data_id): void
    {
        $table_name = $this->dataTable($data_id);

        Database::instance()->exec("CREATE TABLE IF NOT EXISTS `$table_name` (
              `hash` varchar(78) COLLATE utf8mb4_unicode_ci NOT NULL,
              `height` int(11) NOT NULL,
              `blockhash` varchar(78) COLLATE utf8mb4_unicode_ci NOT NULL,
              `cid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
              `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `timestamp` bigint(20) NOT NULL,
              `from` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT '',
              `to` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT '',
              `data` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `public_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
              `signature` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
              PRIMARY KEY (`hash`),
              KEY `height` (`height`),
              KEY `blockhash` (`blockhash`),
              KEY `cid` (`cid`),
              KEY `type` (`type`),
              KEY `from` (`from`),
              KEY `to` (`to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function updateDataId(string $data_id, int $height, string $hash) {
        Database::instance()->exec("INSERT INTO `data_id` (`idx`, `height`, `hash`)
            VALUES ('$data_id', $height, '$hash') 
            ON DUPLICATE KEY UPDATE `idx` = VALUES(`idx`), `height` = VALUES(`height`), `hash` = VALUES(`hash`); ");
    }

    public function deleteDataId(int $height) {
        Database::instance()->exec("DELETE FROM `data_id` WHERE `height` > $height; ");
    }

    public function addData(string $data_id, MainBlock $block) {
        $height = $block->height;
        $blockhash = $block->blockhash;
        $table_name = $this->dataTable($data_id);
        $transactions = $block->transactions;
        $datas = [];

        foreach ($transactions as $transaction) {
            if (gettype($transaction) !== 'Saseul\Model\SignedTransaction') {
                $transaction = new SignedTransaction($transaction);
            }

            $from = $transaction->data['from'] ?? '';
            $to = $transaction->data['to'] ?? '';

            $data = implode("','", [
                $transaction->hash, $height, $blockhash, $transaction->cid, $transaction->type, $transaction->timestamp, $from, $to,
                Database::instance()->escape(json_encode($transaction->data)), $transaction->public_key, $transaction->signature,
            ]);

            $datas[] = "('$data')";
        }

        if (count($datas) > 0) {
            $values = implode(",", $datas);

            Database::instance()->exec("INSERT INTO `$table_name` 
            (`hash`, `height`, `blockhash`, `cid`, `type`, `timestamp`, `from`, `to`, `data`, `public_key`, `signature`) VALUES $values
            ON DUPLICATE KEY UPDATE `hash` = VALUES(`hash`), `height` = VALUES(`height`), 
            `blockhash` = VALUES(`blockhash`), `cid` = VALUES(`cid`), `type` = VALUES(`type`), 
            `timestamp` = VALUES(`timestamp`), `from` = VALUES(`from`), `to` = VALUES(`to`), 
            `data` = VALUES(`data`), `public_key` = VALUES(`public_key`), `signature` = VALUES(`signature`); ");
        }
    }

    public function deleteData(string $data_id, int $height) {
        $data_table = $this->dataTable($data_id);
        Database::instance()->exec("DELETE FROM `$data_table` WHERE `height` >= $height; ");
    }

    public function listData(int $page, int $count, array $query = [], bool $full = false): ?array
    {
        $page = max($page, 0);
        $count = min($count, 100);

        if ($full) {
            $count = min($count, 20);
        }

        $start = $page * $count;

        $data = [];
        $where = '';
        $wheres = [];

        $if = isset($query['cid']) ? $wheres[] = "`cid` = '{$query['cid']}'" : null;
        $if = isset($query['type']) ? $wheres[] = "`type` = '{$query['type']}'" : null;
        $if = isset($query['timehash']) ? $wheres[] = "`hash` > '{$query['timehash']}'" : null;

        if (isset($query['address'])) {
            $wheres[] = "(`from` = '{$query['address']}' OR `to` = '{$query['address']}')";
        } else {
            $if = isset($query['from']) ? $wheres[] = "`from` = '{$query['from']}'" : null;
            $if = isset($query['to']) ? $wheres[] = "`to` = '{$query['to']}'" : null;
        }

        if (count($wheres) > 0) {
            $where = 'WHERE '. implode(' AND ', $wheres);
        }

        $data_id = null;

        do {
            $data_id = is_null($data_id) ? $this->lastDataId() : Protocol::previousDataId($data_id);
            $table_name = $this->dataTable($data_id);

            if ($full) {
                $sql = "SELECT `hash`, `blockhash`, `data`, `public_key`, `signature`
                    FROM `$table_name` $where ORDER BY `hash` DESC LIMIT $start, $count";
            } else {
                $sql = "SELECT `hash`, `blockhash`, `cid`, `type`, `timestamp`, `from`, `to`, `public_key`, `signature`
                    FROM `$table_name` $where ORDER BY `hash` DESC LIMIT $start, $count";
            }

            $rs = Database::instance()->exec($sql);

            while ($row = $rs->fetch_assoc()) {
                $data[] = $row;
            }

            $start = 0;
            $count = $count - count($data);
        } while ($count > 0 && $data_id !== Protocol::dataId());

        return $data;
    }

    // tx hash로 조회하는 기능
    // list로 약식 조회하는 기능, 최대 count 100.
    // list로 풀 조회하는 기능, 최대 count 20.
    // query로 약식 조회하는 기능, 최대 count 100.
    // query로 풀 조회하는 기능, 최대 count 20.
}