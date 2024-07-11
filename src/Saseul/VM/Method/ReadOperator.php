<?php

namespace Saseul\VM\Method;

use Saseul\VM\State;
use Util\Hasher;

trait ReadOperator
{
    public function load_param(array $vars = [])
    {
        $result = null;

        foreach ($vars as $var) {
            if (!is_string($var)) {
                return null;
            }

            if (is_null($result)) {
                $result = $this->signed_data->attributes($var);
            } elseif (is_array($result) && isset($result[$var])) {
                $result = $result[$var];
            } else {
                break;
            }
        }

        return $result;
    }

    public function read_universal(array $vars = [])
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
                $default = $vars[2] ?? null;

                if ($this->process === State::POST) {
                    $cached_data = $this->signed_data->cachedUniversal($status_hash);
                    return $cached_data ?? $this->getUniversalStatus($status_hash, $default);
                } else {
                    return $this->getUniversalStatus($status_hash, $default);
                }
            case State::EXECUTION:
                $default = $vars[2] ?? null;
                return $this->getUniversalStatus($status_hash, $default);
        }

        return null;
    }

    public function read_local(array $vars = [])
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
                $default = $vars[2] ?? null;

                if ($this->process === State::POST) {
                    $cached_data = $this->signed_data->cachedLocal($status_hash);
                    $default = $vars[2] ?? null;
                    return $cached_data ?? $this->getLocalStatus($status_hash, $default);
                } else {
                    return $this->getLocalStatus($status_hash, $default);
                }
            case State::EXECUTION:
                $default = $vars[2] ?? null;
                return $this->getLocalStatus($status_hash, $default);
        }

        return null;
    }
}
