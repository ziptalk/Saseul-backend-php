<?php

namespace Saseul\DataSource;

use ReflectionClass;

class PoolCommands
{
    # base;
    public const IS_RUNNING = 'is_running';

    # tracker;
    public const ADD_PEER_REQUEST = 'add_peer';
    public const PEER_REQUESTS = 'peer';
    public const DRAIN_PEER_REQUESTS = 'drain_peer';

    # policy;
    public const SET_POLICY = 'set_policy';
    public const GET_POLICY = 'get_policy';

    # chunk;
    public const ADD_TX_INDEX = 'add_tx';
    public const EXISTS_TX = 'exists_tx';
    public const INFO_TXS = 'info_txs';
    public const REMOVE_TXS = 'remove_txs';
    public const FLUSH_TXS = 'flush_txs';

    public const ADD_CHUNK_INDEX = 'add_chunk';
    public const COUNT_CHUNKS = 'count_chunk';
    public const REMOVE_CHUNKS = 'remove_chunk';

    public const ADD_HYPOTHESIS_INDEX = 'add_hy';
    public const COUNT_HYPOTHESES = 'count_hy';
    public const REMOVE_HYPOTHESES = 'remove_hy';

    public const ADD_RECEIPT_INDEX = 'add_rcp';
    public const COUNT_RECEIPTS = 'count_rcp';
    public const REMOVE_RECEIPTS = 'remove_rcp';

    # status;
    public const UNIVERSAL_INDEXES = 'uidx';
    public const ADD_UNIVERSAL_INDEXES = 'add_uidx';
    public const SEARCH_UNIVERSAL_INDEXES = 'search_uidx';
    public const COUNT_UNIVERSAL_INDEXES = 'count_uidx';

    public const LOCAL_INDEXES = 'lidx';
    public const ADD_LOCAL_INDEXES = 'add_lidx';
    public const SEARCH_LOCAL_INDEXES = 'search_lidx';
    public const COUNT_LOCAL_INDEXES = 'count_lidx';

    # test;
    public const TEST = 'test';

    public static $_ref = [];

    public static function load()
    {
        $r_class = new ReflectionClass(__CLASS__);

        foreach ($r_class->getConstants() as $command) {
            self::$_ref[$command] = pack('C', count(self::$_ref));
        }
    }

    public static function ref(string $command)
    {
        return self::$_ref[$command] ?? 'Z';
    }
}