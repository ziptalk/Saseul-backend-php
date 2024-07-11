<?php

namespace Saseul\VM;

use Saseul\Config;
use Saseul\Data\Chain;
use Saseul\Data\Env;
use Saseul\Data\MainChain;
use Saseul\Data\ResourceChain;
use Saseul\Model\Chunk;
use Saseul\Model\Hypothesis;
use Saseul\Model\MainBlock;
use Saseul\Model\Method;
use Saseul\Model\Receipt;
use Saseul\Model\SignedRequest;
use Saseul\Model\SignedTransaction;
use Saseul\RPC\Code;
use Util\Clock;
use Util\Hasher;
use Util\Math;

class Machine
{
    public static $instance = null;

    public static function instance(): ?self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected $interpreter;

    protected $contracts;
    protected $requests;
    protected $post_process_contract;

    public $previous_block;
    public $round_timestamp;
    public $transactions;

    public function __construct()
    {
        $this->interpreter = new Interpreter();
        $this->previous_block = new MainBlock();
        $this->round_timestamp = 0;
    }

    public function init(MainBlock $previous_block, int $round_timestamp = 0)
    {
        $legacy = ($round_timestamp > 1666125000000000 && $round_timestamp < 1666130000000000) ||
            $round_timestamp < 1656000000000000;

        if ($legacy) {
            $this->interpreter->reset();
        } else {
            $this->interpreter->reset(true);
        }

        $this->interpreter->init();
        $this->previous_block = $previous_block;
        $this->round_timestamp = $round_timestamp;
    }

    public function setTransactions(array $transactions)
    {
        $this->transactions = [];

        foreach ($transactions as $key => $transaction) {
            if (is_array($transaction)) {
                $this->transactions[$key] = new SignedTransaction($transaction);
            }
        }
    }

    public function transactionCount(): int
    {
        return count($this->transactions);
    }

    public function loadContracts()
    {
        $codes = Code::contracts();

        $this->contracts = $codes['methods'] ?? [];
        $this->post_process_contract = $codes['post_process'] ?? [];
    }

    public function loadRequests()
    {
        $this->requests = Code::requests();
    }

    public function mountContract(SignedTransaction $transaction, ?string &$err_msg = null): bool
    {
        $cid = $transaction->cid ?? Config::rootSpaceId();
        $name = $transaction->type ?? '';
        $code = $this->contracts[$cid][$name] ?? null;

        if (is_null($code)) {
            $err_msg = "There is no contract code: $cid $transaction->type";
            return false;
        }

        if ($cid === Config::rootSpaceId() && in_array($name, Code::SYSTEM_METHODS)) {
            $this->interpreter->set($transaction, $code, new Method());
        } else {
            $this->interpreter->set($transaction, $code, $this->post_process_contract);
        }

        return true;
    }

    public function suitedRequest(SignedRequest $request): ?Method
    {
        $cid = $request->cid ?? Hasher::spaceId(Config::ZERO_ADDRESS, Config::rootSpace());
        $name = $request->type ?? '';

        return $this->requests[$cid][$name] ?? null;
    }

    public function chunk(): Chunk
    {
        $chunk = new Chunk([
            'previous_blockhash' => $this->previous_block->blockhash,
            's_timestamp' => $this->round_timestamp,
            'transactions' => array_keys($this->transactions),
        ]);

        $chunk->signChunk(Env::node());

        return $chunk;
    }

    public function hypothesis(array $chunks = []): Hypothesis
    {
        $hypothesis = new Hypothesis([
            'previous_blockhash' => $this->previous_block->blockhash,
            'chunks' => $chunks,
            's_timestamp' => $this->round_timestamp,
            'thashs' => array_keys($this->transactions),
        ]);

        $hypothesis->signHypothesis(Env::node());

        return $hypothesis;
    }

    public function expectedBlock(array $seal = []): MainBlock
    {
        $expected_block = new MainBlock([
            'height' => $this->previous_block->height + 1,
            'transactions' => $this->transactions,
            's_timestamp' => $this->round_timestamp,
            'seal' => $seal,
            'universal_updates' => $this->interpreter->universal_updates,
            'local_updates' => $this->interpreter->local_updates,
            'previous_blockhash' => $this->previous_block->blockhash,
            'blockhash' => '',
            'validators' => [],
        ]);

        $expected_block->makeBlockhash();

        return $expected_block;
    }

    public function timeValidity(SignedTransaction $transaction, int $timestamp, ?string &$err_msg = ''): bool
    {
        # min < time <= max;
        if ($this->previous_block->s_timestamp < $transaction->timestamp && $transaction->timestamp <= $timestamp) {
            return true;
        }

        $err_msg = "Timestamp must be greater than {$this->previous_block->s_timestamp} and less than $timestamp ";
        return false;
    }

    public function preLoad(array $universal_updates, array $local_updates): void
    {
        foreach ($universal_updates as $key => $update) {
            $old = $update['old'] ?? null;

            if (!is_null($old)) {
                $this->interpreter->setUniversalLoads($key, $old);
            }
        }

        foreach ($local_updates as $key => $update) {
            $old = $update['old'] ?? null;

            if (!is_null($old)) {
                $this->interpreter->setLocalLoads($key, $old);
            }
        }
    }

    public function preCommit(int $confirmed_height, ?string &$err_msg = null): void
    {
        $this->loadContracts();

        ksort($this->transactions);

        # read;
        foreach ($this->transactions as $hash => $transaction) {
            if ($this->timeValidity($transaction, $this->round_timestamp, $err_msg) && $transaction->validity($err_msg)) {
                if ($this->mountContract($transaction, $err_msg)) {
                    if ($this->interpreter->parameterValidity($err_msg)) {
                        $this->interpreter->read();
                        continue;
                    }
                }
            }

            # invalid;
            unset($this->transactions[$hash]);
        }

        # read system status;
        $this->interpreter->addLocalLoads(Config::txCountHash());
        $this->interpreter->addLocalLoads(Config::calculatedHeightHash());
        $this->interpreter->addLocalLoads(Config::recycleResourceHash());

        $miners = Chain::selectMiners($confirmed_height);

        # collect;
        $this->interpreter->loadLocalStatus();
        $this->interpreter->loadMinerStatus($confirmed_height, $miners);
        $this->interpreter->loadUniversalStatus();

        # write;
        foreach ($this->transactions as $hash => $transaction) {
            if ($this->mountContract($transaction, $err_msg)) {
                if ($this->interpreter->execute($err_msg)) {
                    $this->transactions[$hash] = $transaction->obj();
                    continue;
                }
            }

            # invalid;
            unset($this->transactions[$hash]);
        }

        # attach system status;
        $this->setTxCount();
        $this->setResource($confirmed_height, $miners);
    }

    public function commit(MainBlock $block): bool
    {
        if ($block->s_timestamp < Clock::utime() + Config::TIMESTAMP_ERROR_LIMIT) {
            if (MainChain::instance()->write($block)) {
                return true;
            }
        }

        return false;
    }

    public function setTxCount()
    {
        $old_tx_count = (int) $this->interpreter->getLocalStatus(Config::txCountHash(), 0);
        $new_tx_count = $old_tx_count + count($this->transactions);
        $this->interpreter->setLocalStatus(Config::txCountHash(), $new_tx_count);
    }

    public function setResource(int $confirmed_height, array $miners)
    {
        $calculated_height = (int) $this->interpreter->getLocalStatus(Config::calculatedHeightHash(), 0);
        $recycle_resource = $this->interpreter->getLocalStatus(Config::recycleResourceHash(), '0');

        # condition;
        if ($confirmed_height <= $calculated_height) {
            return;
        }

        # targets & amount;
        $target_height = $calculated_height + 1;
        $beneficiaries = [];
        $total_amount = Config::STANDARD_AMOUNT;

        if ($calculated_height < Config::RESOURCE_CONFIRM_COUNT) {
            $total_amount = Config::CREDIT_AMOUNT;
        }

        $total_amount = Math::add($total_amount, $recycle_resource);
        $miner_amount = Math::div($total_amount, 32, 0);

        # miners;
        foreach ($miners as $miner) {
            $hash = Config::resourceHash($miner);
            $amount = $this->interpreter->getUniversalStatus($hash, '0');
            $this->interpreter->setUniversalStatus($hash, Math::add($amount, $miner_amount));

            if (!in_array($miner, $beneficiaries)) {
                $beneficiaries[] = $miner;
            }
        }

        # beneficiary;
        $block = ResourceChain::instance()->block($target_height);

        foreach ($block->receipts as $receipt) {
            if (is_array($receipt)) {
                $receipt = new Receipt($receipt);

                if (!in_array($receipt->beneficiary, $beneficiaries)) {
                    $beneficiaries[] = $receipt->beneficiary;
                }
            }
        }

        $beneficiaries = array_unique($beneficiaries);
        $remains = Math::sub($total_amount, Math::mul($miner_amount, count($miners)));
        $beneficiary_amount = Math::div($remains, count($beneficiaries), 0);

        foreach ($beneficiaries as $address) {
            $hash = Config::resourceHash($address);
            $amount = $this->interpreter->getUniversalStatus($hash, '0');
            $this->interpreter->setUniversalStatus($hash, Math::add($amount, $beneficiary_amount));
        }

        $this->interpreter->setLocalStatus(Config::calculatedHeightHash(), $target_height);
        $this->interpreter->setLocalStatus(Config::recycleResourceHash(), '0');
    }

    public function txValidity(SignedTransaction $transaction, ?string &$err_msg = ''): bool
    {
        $size = strlen($transaction->json());

        if ($size > Config::TX_SIZE_LIMIT) {
            $err_msg = 'The length of the signed transaction must be less than '. Config::TX_SIZE_LIMIT. ' characters.';
            return false;
        }

        if (!$transaction->validity($err_msg)) {
            return false;
        }

        $err_msg = null;

        $last_block = MainChain::instance()->lastBlock();
        $round_timestamp = Clock::utime() + Config::TIMESTAMP_ERROR_LIMIT;
        $confirmed_height = Chain::confirmedHeight($round_timestamp);

        $this->init($last_block, $round_timestamp);
        $this->setTransactions([$transaction->hash => $transaction->obj()]);
        $this->preCommit($confirmed_height, $err_msg);

        if (!is_null($err_msg)) {
            return false;
        }

        return true;
    }

    public function weight(SignedTransaction $transaction, ?string &$err_msg = ''): ?string
    {
        if ($this->txValidity($transaction, $err_msg)) {
            return (string) $this->interpreter->weight;
        }

        return $this->interpreter->weight;
    }

    public function response(SignedRequest $request, ?string &$err_msg = '')
    {
        if (!$request->validity($err_msg)) {
            return null;
        }

        $this->interpreter->reset();
        $this->interpreter->init('request');
        $this->loadRequests();

        $code = $this->suitedRequest($request);

        if (is_null($code)) {
            $err_msg = "There is no request code: $request->type ";
            return null;
        }

        $result = null;
        $this->interpreter->set($request, $code, new Method());

        if ($this->interpreter->parameterValidity($err_msg)) {
            $this->interpreter->read();
            $this->interpreter->loadLocalStatus();
            $this->interpreter->loadUniversalStatus();

            if ($this->interpreter->execute($result)) {
                return $result;
            } else {
                $err_msg = $result;
                return null;
            }
        }

        return null;
    }
}