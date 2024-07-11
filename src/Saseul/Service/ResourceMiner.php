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
use Saseul\Data\ResourceChain;
use Saseul\Data\Tracker;
use Saseul\DataSource\PoolClient;
use Saseul\Model\Receipt;
use Saseul\Model\ResourceBlock;
use Saseul\Staff\MasterClient;
use Saseul\Staff\Observer;
use Saseul\Staff\ProcessManager;
use Util\Clock;
use Util\Hasher;
use Util\Timer;

class ResourceMiner extends Service
{
    protected $_iterate = 100000;
    protected $mining_timer;
    protected $forked_height = 0;

    public function __construct()
    {
        if (Config::$_environment === 'process') {
            if (ProcessManager::isRunning(ProcessManager::RESOURCE_MINER)) {
                Logger::log('The resource miner process is already running. ');
                exit;
            }

            cli_set_process_title('saseul: resource_miner');
            ProcessManager::save(ProcessManager::RESOURCE_MINER);
        }

        $this->mining_timer = new Timer();
        PoolClient::instance()->mode('rewind');
    }

    public function __destruct()
    {
        if (ProcessManager::pid(ProcessManager::RESOURCE_MINER) === getmypid()) {
            Logger::log('Resource miner process has been successfully removed. ');
            ProcessManager::delete(ProcessManager::RESOURCE_MINER);
        }
    }

    public function stop()
    {
        Logger::log('Resource miner process end. ');
        exit;
    }

    public function init()
    {
        $this->addRoutine([ $this, 'operation'], 100000);

        Logger::log('The resource miner process has started. ');
    }

    public function operation()
    {
        # load;
        $last_block = $this->getLastBlock();

        # sync;
        $sync = $this->sync($last_block);
        $mining = PoolClient::instance()->getPolicy('mining');

        if (!$sync && $mining) {
            $this->dataPulling($last_block);
            $this->mining($last_block);
        }
    }

    public function getLastBlock(): ResourceBlock
    {
        $last_block = ResourceChain::instance()->lastBlock();

        if (MainChain::instance()->lastHeight() < $last_block->main_height) {
            ResourceChain::instance()->remove(Chain::fixedHeight() + 1);
            usleep(300000);
            $last_block = ResourceChain::instance()->lastBlock();
        }

        return $last_block;
    }

    public function dataPulling(ResourceBlock $last_block)
    {
        $peers = Tracker::getPeers();
        $hosts = Tracker::hostMap(Env::peer()->address(), $peers);
        Observer::instance()->seeResourceBroadcasts($hosts, $last_block);
    }

    public function sync(ResourceBlock $last_block, ?string &$err_msg = ''): bool
    {
        $peers = Tracker::getPeers();
        $hosts = $this->hosts($peers);
        $sync_hosts = $this->syncHosts($hosts);

        $hosts = $sync_hosts['hosts'];
        $longest = $sync_hosts['longest'];

        $fixed_height = Chain::fixedHeight();
        $decisions = Observer::instance()->seeResourceBlocks($hosts, $fixed_height + 1);

        $err_msg = '';
        $pull = false;

        foreach ($decisions as $case) {
            if (!$pull) {
                $blocks = (array) $case['blocks'];
                $is_longest = ($case['host'] === $longest['host'] && $longest['sync_limit'] > $last_block->height);

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

    public function syncHosts($hosts): array
    {
        # see round;
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
                $block = new ResourceBlock($block);

                if ($this->commit($block, $is_longest, $err_msg)) {
                    $result = true;
                }
            }
        }

        return $result;
    }

    public function mining(ResourceBlock $last_block): bool
    {
        $receipts = Bunch::listReceipt($last_block->main_blockhash);
        $main_block = MainChain::instance()->lastBlock();

        if ($last_block->height > 0 && $main_block->height > 0 &&
            $last_block->main_height <= $main_block->height) {

            # first 20 block ~> genesis node
            if ($last_block->height <= Config::RESOURCE_CONFIRM_COUNT &&
                Env::node()->address() !== Config::$_genesis_address) {
                return false;
            }

            foreach ($receipts as $signer => $receipt) {
                if (is_array($receipt)) {
                    $receipt = new Receipt($receipt);
                    $block = MainChain::instance()->block($receipt->previous_blockhash);

                    if ($block->height > 0) {
                        $receipts[$signer] = $receipt->obj();
                    }
                }
            }

            $expected_block = new ResourceBlock([
                'height' => $last_block->height + 1,
                'blockhash' => '',
                'previous_blockhash' => $last_block->blockhash,
                'nonce' => '',
                'timestamp' => '',
                'difficulty' => ResourceChain::instance()->difficulty($last_block->height + 1),
                'main_height' => $main_block->height,
                'main_blockhash' => $main_block->blockhash,
                'validator' => Env::node()->address(),
                'miner' => Env::owner(),
                'receipts' => $receipts
            ]);

            $this->mining_timer->start();

            do {
                $expected_block->timestamp = Clock::utime();
                $expected_block->nonce = $this->nonce();

                if ($this->mining_timer->fullInterval() > Config::MINING_INTERVAL) {
                    break;
                }
            } while ($expected_block->nonceValidity() === false);

            $expected_block->blockhash = $expected_block->blockhash();

            # sync check
            $sync = $this->sync($last_block);
            $mining = PoolClient::instance()->getPolicy('mining');

            if (!$sync && $mining) {
                return $this->commit($expected_block);
            }
        }

        return false;
    }

    public function commit(ResourceBlock $resource_block, bool $is_longest = false, ?string &$err_msg = ''): bool
    {
        $previous_height = max($resource_block->height - 1, 1);
        $previous_block = ResourceChain::instance()->block($previous_height);
        $fork_a = HardFork::resourceConditionA($resource_block);
        $block_validity = ($fork_a && $resource_block->blockValidityLegacy()) || $resource_block->blockValidity();

        $available = ($block_validity &&
            $previous_block->height + 1 === $resource_block->height &&
            $previous_block->blockhash === $resource_block->previous_blockhash &&
            $previous_block->main_height <= $resource_block->main_height &&
            $previous_block->timestamp + Config::MAIN_CHAIN_INTERVAL < $resource_block->timestamp);

        if (!$available) {
            $err_msg = 'Invalid resource block';
            return false;
        }

        $r_waiting = PoolClient::instance()->getPolicy('resource_chain_waiting');

        if ($is_longest && $r_waiting) {
            $err_msg = 'Excluding forked blocks..';
            return false;
        }

        if ($is_longest) {
            $fixed_height = Chain::fixedHeight();
            $old_block = ResourceChain::instance()->block($resource_block->height);
            $main_block = MainChain::instance()->block($resource_block->main_height);

            $resource_exists = $old_block->height > 0;
            $main_exists = $main_block->height > 0;

            $main_fork = ($main_block->blockhash !== $resource_block->main_blockhash) &&
                !HardFork::resourceCondition($resource_block);
            $fork = $main_block->blockhash === $resource_block->main_blockhash &&
                $resource_block->blockhash !== $old_block->blockhash &&
                $resource_block->height > $fixed_height;

            $waiting = PoolClient::instance()->getPolicy('main_chain_waiting');

            if ($resource_exists && $main_exists && $fork) {
                $err_msg = 'Resource chain fork. ';
                PoolClient::instance()->setPolicy('main_chain_waiting', false);
//                ResourceChain::instance()->remove(max($fixed_height + 1, $previous_height));
                ResourceChain::instance()->remove($fixed_height + 1);
                return true;
            } elseif (($main_exists && $main_fork) || $waiting) {
                $err_msg = 'Main chain fork. ';

//                if ($this->forked_height === 0) {
//                    $this->forked_height = $fixed_height;
//                    return false;
//                } else if ($this->forked_height === $fixed_height) {
//                    return false;
//                }

                PoolClient::instance()->setPolicy('chain_maker', false);

                while (ProcessManager::isRunning(ProcessManager::CHAIN_MAKER)) {
                    sleep(1);
                }

                MasterClient::instance()->send('reload');
                return true;
            }
        }

        $last_height = ResourceChain::instance()->lastHeight();

        if ($resource_block->height === $last_height + 1) {
            $main_block = MainChain::instance()->block($resource_block->main_height);
            $condition = $main_block->height > 0 && $main_block->blockhash === $resource_block->main_blockhash;
            $condition = HardFork::resourceCondition($resource_block) || $condition;

            if ($condition && $resource_block->timestamp < Clock::utime() + Config::TIMESTAMP_ERROR_LIMIT) {
                PoolClient::instance()->setPolicy('main_chain_waiting', false);
                ResourceChain::instance()->write($resource_block);
                Chain::updateFixedHeight();
                Bunch::removeReceipt($resource_block->main_blockhash);
                return true;
            }
        }

        $err_msg = 'Commit failed';
        return false;
    }

    public function nonce(): string
    {
        return bin2hex(random_bytes(Hasher::HASH_BYTES));
    }
}