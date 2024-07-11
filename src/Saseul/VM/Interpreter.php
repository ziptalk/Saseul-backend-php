<?php

namespace Saseul\VM;

use Saseul\Config;
use Saseul\Data\ResourceChain;
use Saseul\Data\Status;
use Saseul\Model\Code;
use Saseul\Model\Method;
use Saseul\Model\Receipt;
use Saseul\Model\SignedData;
use Saseul\VM\Method\ArithmeticOperator;
use Saseul\VM\Method\BasicOperator;
use Saseul\VM\Method\CastOperator;
use Saseul\VM\Method\ChainOperator;
use Saseul\VM\Method\ComparisonOperator;
use Saseul\VM\Method\ReadOperator;
use Saseul\VM\Method\UtilOperator;
use Saseul\VM\Method\WriteOperator;
use Util\Hasher;
use Util\Signer;

class Interpreter
{
    use BasicOperator;
    use ArithmeticOperator;
    use ComparisonOperator;
    use CastOperator;
    use UtilOperator;
    use ReadOperator;

    # contract
    use WriteOperator;

    # request
    use ChainOperator;

    protected $mode;

    protected $signed_data;
    protected $code;
    protected $post_process;
    protected $break;
    protected $result;
    public $weight;

    protected $methods = [];
    protected $state = State::NULL;
    protected $process = State::NULL;

    public $universals = [];
    public $locals = [];

    public $universal_updates = [];
    public $local_updates = [];

    public function __construct()
    {
        $this->signed_data = new SignedData();
        $this->code = new Method();
        $this->post_process = new Method();
    }

    public function reset($all = false): void
    {
        $this->signed_data = null;
        $this->code = null;
        $this->post_process = null;
        $this->break = false;
        $this->result = '';
        $this->weight = 0;

        if ($all) {
            $this->state = State::NULL;
            $this->process = State::NULL;

            $this->universals = [];
            $this->locals = [];
        }

        $this->universal_updates = [];
        $this->local_updates = [];
    }

    public function init(string $mode = 'transaction'): void
    {
        if ($this->mode !== $mode) {
            $this->mode = $mode;
            $this->methods = [];

            $this->loadMethod('Saseul\VM\Method\BasicOperator');
            $this->loadMethod('Saseul\VM\Method\ArithmeticOperator');
            $this->loadMethod('Saseul\VM\Method\ComparisonOperator');
            $this->loadMethod('Saseul\VM\Method\UtilOperator');
            $this->loadMethod('Saseul\VM\Method\CastOperator');
            $this->loadMethod('Saseul\VM\Method\ReadOperator');

            if ($this->mode === 'transaction') {
                $this->loadMethod('Saseul\VM\Method\WriteOperator');
            } else {
                $this->loadMethod('Saseul\VM\Method\ChainOperator');
            }
        }
    }

    public function loadMethod(string $trait_name): void
    {
        $this->methods = array_merge($this->methods, get_class_methods($trait_name));
    }

    public function set(SignedData $data, Code $code, Code $post_process)
    {
        $this->signed_data = $data;
        $this->code = $code;
        $this->post_process = $post_process;
        $this->break = false;
        $this->weight = 0;
        $this->result = 'Conditional Error';
        $this->setDefaultValue();
    }

    public function process($abi)
    {
        if (is_array($abi)) {
            foreach ($abi as $key => $item) {
                $prefix = $key[0] ?? '';
                $method = substr($key, 1);
                $vars = $this->process($item);

                if ($prefix === '$' && in_array($method, $this->methods) && is_array($vars)) {
                    return $this->$method($vars);
                } else {
                    $abi[$key] = $vars;
                }
            }
        }

        return $abi;
    }

    public function setDefaultValue(): void
    {
        # common
        # version;
        if (is_null($this->signed_data->attributes('version'))) {
            $this->signed_data->attributes('version', Config::$_version);
        }

        foreach ($this->code->parameters() as $name => $parameter) {
            $requirements = (bool) ($parameter['requirements'] ?? false);

            if (!$requirements && is_null($this->signed_data->attributes($name))) {
                $default = $parameter['default'] ?? null;
                $this->signed_data->attributes($name, $default);
            }
        }

        # contract
        if ($this->mode === 'transaction') {
            # from;
            if (is_null($this->signed_data->attributes('from'))) {
                $this->signed_data->attributes('from', Signer::address($this->signed_data->public_key));
            }

            # hash;
            if (is_null($this->signed_data->attributes('hash'))) {
                $this->signed_data->attributes('hash', $this->signed_data->hash);
            }

            # size;
            if (is_null($this->signed_data->attributes('size'))) {
                $this->signed_data->attributes('size', $this->signed_data->size());
            }

            $this->weight += (int) $this->signed_data->attributes('size');
        }
    }

    public function parameterValidity(?string &$err_msg = null): bool
    {
        # contract
        if ($this->mode === 'transaction') {
            $from = $this->signed_data->attributes('from');

            # from;
            if ($from !== Signer::address($this->signed_data->public_key)) {
                $err_msg = "Invalid from address: $from ";
                return false;
            }
        }

        # common
        foreach ($this->code->parameters() as $parameter)
        {
            if (is_array($parameter)) {
                $parameter = new Parameter($parameter);
            }

            if (!$parameter->objValidity()) {
                $err_msg = $this->mode. ' error. ';
                return false;
            }

            $value = $this->signed_data->attributes($parameter->name()) ?? $parameter->default();

            if (!$parameter->structureValidity($value, $err_msg) ||
                !$parameter->typeValidity($value, $err_msg)) {
                return false;
            }
        }

        return true;
    }

    public function read(): void
    {
        $this->state = State::READ;
        $this->process = State::MAIN;

        # common
        foreach ($this->code->executions() as $execution) {
            $this->process($execution);
        }

        $this->process = State::POST;

        # post process;
        foreach ($this->post_process->executions() as $execution) {
            $this->process($execution);
        }

    }

    public function execute(&$result = null): bool
    {
        $executions = $this->code->executions();
        $post_executions = $this->post_process->executions();

        $this->state = State::CONDITION;
        $this->process = State::MAIN;

        # main, condition
        foreach ($executions as $key => $execution) {
            $executions[$key] = $this->process($execution);

            if ($this->break) {
                $result = $this->result;
                return false;
            }
        }

        $process_length = strlen(Hasher::string($executions));

        switch ($this->mode) {
            case 'transaction':
                if ($process_length > Config::TX_SIZE_LIMIT) {
                    $result = 'Too long processing. ';
                    return false;
                }
                break;
            default:
                if ($process_length > Config::BLOCK_TX_SIZE_LIMIT) {
                    $result = 'Too long processing. ';
                    return false;
                }
                break;
        }

        # post, condition
        $this->process = State::POST;

        foreach ($post_executions as $key => $execution) {
            $post_executions[$key] = $this->process($execution);

            if ($this->break) {
                $result = $this->result;
                return false;
            }
        }

        # main, execution
        $this->state = State::EXECUTION;
        $this->process = State::MAIN;

        foreach ($executions as $key => $execution) {
            $executions[$key] = $this->process($execution);

            if ($this->break) {
                $result = $this->result;
                return true;
            }
        }

        # post, execution
        $this->process = State::POST;

        foreach ($post_executions as $key => $execution) {
            $post_executions[$key] = $this->process($execution);

            if ($this->break) {
                $result = $this->result;
                return true;
            }
        }

        return true;
    }

    public function loadLocalStatus()
    {
        if (count($this->locals) > 0) {
            $keys = array_keys($this->locals);
            $this->locals = Status::instance()->localStatuses($keys) ?? [];
        }
    }

    public function loadUniversalStatus()
    {
        if (count($this->universals) > 0) {
            $keys = array_keys($this->universals);
            $this->universals = Status::instance()->universalStatuses($keys) ?? [];
        }
    }

    public function loadMinerStatus(int $confirmed_height, array $miners)
    {
        $calculated_height = (int) $this->getLocalStatus(Config::calculatedHeightHash(), 0);

        # condition;
        if ($confirmed_height <= $calculated_height) {
            return;
        }

        # miners;
        foreach ($miners as $miner) {
            $this->addUniversalLoads(Config::resourceHash($miner));
        }

        # beneficiary;
        $block = ResourceChain::instance()->block($calculated_height + 1);

        foreach ($block->receipts as $receipt) {
            if (is_array($receipt)) {
                $receipt = new Receipt($receipt);
                $this->addUniversalLoads(Config::resourceHash($receipt->beneficiary));
            }
        }
    }

    public function addLocalLoads(string $status_hash)
    {
        $status_hash = Hasher::fillHash($status_hash);
        
        if (!isset($this->locals[$status_hash])) {
            $this->locals[$status_hash] = [];
        }
    }

    public function addUniversalLoads(string $status_hash)
    {
        $status_hash = Hasher::fillHash($status_hash);

        if (!isset($this->universals[$status_hash])) {
            $this->universals[$status_hash] = [];
        }
    }

    public function setLocalLoads(string $status_hash, $value): void
    {
        $status_hash = Hasher::fillHash($status_hash);

        $this->locals[$status_hash] = $value;
    }

    public function setUniversalLoads(string $status_hash, $value): void
    {
        $status_hash = Hasher::fillHash($status_hash);

        $this->universals[$status_hash] = $value;
    }

    public function getLocalStatus(string $status_hash, $default = null)
    {
        $status_hash = Hasher::fillHash($status_hash);

        return $this->locals[$status_hash] ?? $default;
    }

    public function getUniversalStatus(string $status_hash, $default = null)
    {
        $status_hash = Hasher::fillHash($status_hash);

        return $this->universals[$status_hash] ?? $default;
    }

    public function setUniversalStatus(string $status_hash, $value): bool
    {
        if (isset($this->universal_updates[$status_hash])) {
            $this->universal_updates[$status_hash]['new'] = $value;
        } else {
            $this->universal_updates[$status_hash] = [
                'old' => $this->getUniversalStatus($status_hash),
                'new' => $value
            ];
        }

        $status_hash = Hasher::fillHash($status_hash);
        $this->universals[$status_hash] = $value;

        return true;
    }

    public function setLocalStatus(string $status_hash, $value): bool
    {
        if (isset($this->local_updates[$status_hash])) {
            $this->local_updates[$status_hash]['new'] = $value;
        } else {
            $this->local_updates[$status_hash] = [
                'old' => $this->getLocalStatus($status_hash),
                'new' => $value
            ];
        }

        $status_hash = Hasher::fillHash($status_hash);
        $this->locals[$status_hash] = $value;

        return true;
    }
}
