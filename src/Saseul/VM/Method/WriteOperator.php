<?php

namespace Saseul\VM\Method;

use Saseul\Config;
use Saseul\VM\State;
use Util\Hasher;

trait WriteOperator
{
    # only contract
    public function write_universal(array $vars = [])
    {
        $attr = $vars[0] ?? '';
        $key = $vars[1] ?? '';

        if ($this->process === State::MAIN) {
            $status_hash = Hasher::statusHash($this->code->writer(), $this->code->space(), $attr, $key) ?? null;
        } elseif ($this->process === State::POST) {
            $status_hash = Hasher::statusHash($this->post_process->writer(), $this->post_process->space(), $attr, $key) ?? null;
        } else {
            $status_hash = null;
        }

        if (is_null($status_hash)) {
            return null;
        }

        switch ($this->state) {
            case State::READ:
                $this->addUniversalLoads($status_hash);
                break;
            case State::CONDITION:
                $value = $vars[2] ?? null;
                $length = strlen(Hasher::string($value));

                if ($length > Config::STATUS_SIZE_LIMIT) {
                    $this->break = true;
                    $this->result = 'Too long status values. maximum size: '. Config::STATUS_SIZE_LIMIT;
                }

                if ($this->process === State::MAIN) {
                    $this->signed_data->cachedUniversal($status_hash, $value);
                    $this->weight += strlen($status_hash) + $length;
                }
                return [ '$write_universal' => $vars ];
            case State::EXECUTION:
                $value = $vars[2] ?? null;
                return $this->setUniversalStatus($status_hash, $value);
        }

        return null;
    }

    public function write_local(array $vars = [])
    {
        $attr = $vars[0] ?? '';
        $key = $vars[1] ?? '';

        if ($this->process === State::MAIN) {
            $status_hash = Hasher::statusHash($this->code->writer(), $this->code->space(), $attr, $key) ?? null;
        } elseif ($this->process === State::POST) {
            $status_hash = Hasher::statusHash($this->post_process->writer(), $this->post_process->space(), $attr, $key) ?? null;
        } else {
            $status_hash = null;
        }

        if (is_null($status_hash)) {
            return null;
        }

        switch ($this->state) {
            case State::READ:
                $this->addLocalLoads($status_hash);
                break;
            case State::CONDITION:
                $value = $vars[2] ?? null;
                $length = strlen(Hasher::string($value));

                if ($length > Config::STATUS_SIZE_LIMIT) {
                    $this->break = true;
                    $this->result = 'Too long status values. maximum size: '. Config::STATUS_SIZE_LIMIT;
                }

                if ($this->process === State::MAIN) {
                    $this->signed_data->cachedLocal($status_hash, $value);
                    $this->weight += strlen($status_hash) + ($length * 1000000000);
                }
                return [ '$write_local' => $vars ];
            case State::EXECUTION:
                $value = $vars[2] ?? null;
                return $this->setLocalStatus($status_hash, $value);
        }

        return null;
    }
}
