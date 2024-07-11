<?php

namespace Saseul\Service;

use Core\Logger;
use Core\Service;
use Patch\HardFork;
use Saseul\Config;
use Saseul\Data\Bunch;
use Saseul\Data\Chain;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Data\Tracker;
use Saseul\DataSource\PoolClient;
use Saseul\Model\Hypothesis;
use Saseul\Model\MainBlock;
use Saseul\Staff\Observer;
use Saseul\Staff\ProcessManager;
use Saseul\VM\Machine;
use Util\Clock;

class ChainMaker extends Service
{
    protected $_iterate = 100000;

    # TODO: Memory
    # TODO: Control confidence scores.
    public function __construct()
    {
        if (Config::$_environment === 'process') {
            if (ProcessManager::isRunning(ProcessManager::CHAIN_MAKER)) {
                Logger::log('The chain maker process is already running. ');
                exit;
            }

            cli_set_process_title('saseul: chain_maker');
            ProcessManager::save(ProcessManager::CHAIN_MAKER);
        }

        PoolClient::instance()->mode('rewind');
    }

    public function __destruct()
    {
        if (ProcessManager::pid(ProcessManager::CHAIN_MAKER) === getmypid()) {
            Logger::log('Chain maker process has been successfully removed. ');
            ProcessManager::delete(ProcessManager::CHAIN_MAKER);
        }
    }

    public function stop()
    {
        Logger::log('Chain maker process end. ');
        exit;
    }

    public function init()
    {
        $this->addRoutine([ $this, 'operation'], 100000);

        Logger::log('The chain maker process has started. ');
    }

    public function operation()
    {
        # init;
        $last_block = MainChain::instance()->lastBlock();

        $this->refresh($last_block);

        # sync & consensus;
        $err_msg = '';
        $sync = $this->sync($last_block, $err_msg);

        if (!$sync) {
            $this->consensus($last_block, $err_msg);
        }
    }

    public function refresh(MainBlock $last_block)
    {
        Bunch::clean($last_block);

        $info = Bunch::infoTxs();
        $count = (int) ($info['count'] ?? 0);
        $size = (int) ($info['size'] ?? 0);

        $count_limit = Config::BLOCK_TX_COUNT_LIMIT * 2;
        $size_limit = Config::BLOCK_TX_SIZE_LIMIT * 2;

        if ($count > $count_limit || $size > $size_limit) {
            PoolClient::instance()->flushTxs();
        }
    }

    public function sync(MainBlock $last_block, ?string &$err_msg = ''): bool
    {
        $peers = Tracker::getPeers();
        $hosts = $this->hosts($peers);
        $sync_hosts = $this->syncHosts($hosts);

        $hosts = $sync_hosts['hosts'];
        $longest = $sync_hosts['longest'];

        $decisions = Observer::instance()->seeBlocks($hosts, $last_block->height + 1);

        $err_msg = '';
        $pull = false;

        foreach ($decisions as $case) {
            if (!$pull) {
                $blocks = (array) $case['blocks'];
                $is_longest = ($case['host'] === $longest['host']);

                if ($this->pull($blocks, $is_longest, $err_msg)) {
                    $pull = true;
                }
            }
        }

        return $pull;
    }

    public function hosts(array $peers): array
    {
        $hosts = [];

        foreach ($peers as $peer) {
            $hosts[] = $peer['host'];
        }

        return $hosts;
    }

    public function syncHosts(array $hosts): array
    {
        # see mining_round;
        $height = max(Chain::fixedHeight(), 1);
        $rounds = Observer::instance()->seeMiningRounds($hosts, $height);

        $hosts = [];
        $longest = [];
        $max_sync_limit = 0;
        $max_hash = '';

        foreach ($rounds as $hash => $case) {
            foreach ($case as $round) {
                if ($round['sync_limit'] > $max_sync_limit) {
                    $longest = $round;
                    $max_sync_limit = $round['sync_limit'];
                    $max_hash = $hash;
                }
            }
        }

        if (isset($longest['host'])) {
            $hosts[] = $longest['host'];
        }

        foreach ($rounds as $hash => $case) {
            array_multisort(
                array_column($case, 'sync_limit'), SORT_DESC,
                array_column($case, 'exec_time'), SORT_ASC,
                $case
            );

            $hosts[] = $case[0]['host'];

            if (count($case) > 1) {
                if ($hash === $max_hash) {
                    $hosts[] = $case[1]['host'];

                    for ($i = 0; $i < 3; $i++) {
                        $j = rand(1, count($case) - 1);
                        $hosts[] = $case[$j]['host'];
                    }
                } else {
                    $j = rand(1, count($case) - 1);
                    $hosts[] = $case[$j]['host'];
                }
            }
        }

        return [
            'longest' => $longest,
            'hosts' => array_values(array_unique($hosts))
        ];
    }

    public function pull(array $blocks, bool $is_longest = false, ?string &$err_msg = ''): bool
    {
        $result = false;

        foreach ($blocks as $block) {
            if (is_array($block)) {
                $block = new MainBlock($block);

                if (!$this->append($block, $is_longest, $err_msg)) {
                    break;
                }

                $result = true;
            }
        }

        return $result;
    }

    public function append(MainBlock $block, bool $is_longest = false, ?string &$err_msg = ''): bool
    {
        $machine = Machine::instance();

        $last_block = MainChain::instance()->lastBlock();
        $confirmed_height = Chain::confirmedHeight($block->s_timestamp);
        $confirmed_height = HardFork::confirmedHeight($block) ? HardFork::forkHeight($confirmed_height) : $confirmed_height;
        $validators = HardFork::validators($block) ? HardFork::forkValidators() : Chain::selectValidators($confirmed_height);

        if ($block->height !== $last_block->height + 1 || count($validators) === 0) {
            $err_msg = 'Waiting for resource blocks.. ';
            PoolClient::instance()->setPolicy('main_chain_waiting', false);
            return true;
        }

        $block->validators = $validators;

        $machine->init($last_block, $block->s_timestamp);
        $machine->setTransactions($block->transactions);

        $machine->preLoad($block->universal_updates, $block->local_updates);
        $machine->preCommit($confirmed_height, $err_msg);

        $expected_block = $machine->expectedBlock();

        # validity;
        $validity = $block->validity() && (HardFork::mainCondition($block) || count($machine->transactions) > 0) &&
            $block->blockhash === $expected_block->blockhash;

        # continuity;
        $continuity = $block->height === $last_block->height + 1 &&
            $block->previous_blockhash === $last_block->blockhash &&
            $block->s_timestamp >= $last_block->s_timestamp + Config::MAIN_CHAIN_INTERVAL;

        if ($validity && $continuity) {
            PoolClient::instance()->setPolicy('main_chain_waiting', false);
            $machine->commit($block);
            return true;
        } elseif (!$continuity && $is_longest) {
            $err_msg = 'Main chain fork. ';
            Logger::log($err_msg);
//            Logger::log("Last Block height: $last_block->height");
//            Logger::log("Last Block hash: $last_block->blockhash");
//            Logger::log("Block height: $block->height");
//            Logger::log("Block hash: $block->blockhash");
            PoolClient::instance()->setPolicy('main_chain_waiting', true);
            return false;
        }

        $err_msg = 'Blockhash is different. ';
        Logger::log($err_msg);
        return false;
    }

    public function consensus(MainBlock $last_block, ?string &$err_msg = '')
    {
        $round_key = $last_block->blockhash;
        $chunks = Bunch::listChunk($round_key);

        $now = Clock::ufloortime();
        $min = max($now - Config::REFRESH_INTERVAL, $last_block->s_timestamp);
        $round_timestamp = $now;
        $refresh = false;

        if (count($chunks) > 0) {
            $round_timestamp = max(array_column($chunks, 's_timestamp'));

            if ($round_timestamp < $min) {
                $refresh = true;
                $round_timestamp = $now;
            }
        }

        # check transactions;
        $transactions = Bunch::listTx($round_timestamp);

        if (count($transactions) === 0) {
            return;
        }

        # check validator;
        $confirmed_height = Chain::confirmedHeight($round_timestamp);
        $validators = Chain::selectValidators($confirmed_height);
        $main = $validators[8] ?? null;
        $address = Env::node()->address();

        if (!in_array($address, $validators) || $last_block->s_timestamp >= $round_timestamp) {
            return;
        }

        # get valid chunks;
        foreach ($chunks as $signer => $chunk) {
            if (!in_array($signer, $validators)) {
                unset($chunks[$signer]);
            }
        }

        $machine = Machine::instance();

        # pre-commit;
        $machine->init($last_block, $round_timestamp);
        $machine->setTransactions($transactions);
        $machine->preCommit($confirmed_height, $err_msg);

        # chunk;
        $chunk = $machine->chunk();

        $chunk_hashs = array_map(function ($chunk) { return ($chunk['chunk_hash'] ?? ''); }, $chunks);
        $chunk_hashs[$address] = $chunk->chunk_hash;

        # hypothesis;
        $hypothesis = $machine->hypothesis($chunk_hashs);

        # expected_block;
        $expected_block = $machine->expectedBlock();
        $expected_block->validators = $validators;

        $condition = HardFork::mainCondition($expected_block) || $machine->transactionCount() > 0;

        if (!$condition) {
            return;
        }

        if ($refresh || !isset($chunks[$address])) {
            Bunch::addChunk($chunk);
        }

        if ($address === $main) {
            Bunch::addHypothesis($hypothesis);
            $hypotheses = Bunch::listHypothesis($last_block->blockhash);
        } else {
            $chunks = Bunch::listChunk($last_block->blockhash);

            if (!$this->votes($validators, array_keys($chunks))) {
                return;
            }

            Bunch::addHypothesis($hypothesis);
            $hypotheses = Bunch::listHypothesis($last_block->blockhash);

            if (!$this->votes($validators, array_keys($hypotheses))) {
                return;
            }

            # one more sync
//            if ($this->sync($last_block, $err_msg)) {
//                return;
//            }
        }

        # add seal;
        $expected_block = $this->seal($expected_block, $hypotheses);

        $validity = $expected_block->validity() &&
            $expected_block->height === $last_block->height + 1 &&
            (HardFork::mainCondition($expected_block) || count($expected_block->transactions) > 0);

        $continuity = $expected_block->previous_blockhash === $last_block->blockhash &&
            $expected_block->s_timestamp >= $last_block->s_timestamp + Config::MAIN_CHAIN_INTERVAL;

        if ($validity && $continuity) {
            Machine::instance()->commit($expected_block);
        }
    }

    public function seal(MainBlock $expected_block, array $hypotheses): MainBlock
    {
        $sealed_s_timestamp = array_column($hypotheses, 's_timestamp');
        $sealed_s_timestamp = max($sealed_s_timestamp);

        $sealed_hypotheses = array_map(function ($hypothesis) {
            $hypothesis = new Hypothesis($hypothesis);
            return $hypothesis->seal();
        }, $hypotheses);

        $expected_block->seal = $sealed_hypotheses;
        $expected_block->s_timestamp = $sealed_s_timestamp;

        return $expected_block;
    }

    public function votes(array $validators, array $datas): bool
    {
        $quorum = count($validators) * Config::MAIN_CONSENSUS_PER;
        $votes_cast = 0;

        foreach ($validators as $validator) {
            if (in_array($validator, $datas)) {
                $votes_cast = $votes_cast + 1;
            }
        }

        if ($votes_cast > $quorum) {
            return true;
        }

        return false;
    }
}