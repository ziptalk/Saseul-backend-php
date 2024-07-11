<?php

namespace Saseul\Model;

use Saseul\VM\Parameter;
use Util\Hasher;

class Code
{
    protected $type = 'code';
    protected $machine;
    protected $name;
    protected $version;
    protected $space;
    protected $writer;
    protected $parameters = [];
    protected $executions = [];

    public function cid(): string
    {
        return Hasher::spaceId($this->writer(), $this->space());
    }

    public function type(?string $type = null): ?string
    {
        if (is_null($type)) {
            return $this->type;
        }

        return ($this->type = $type);
    }

    public function machine(?string $machine = null): ?string
    {
        if (is_null($machine)) {
            return $this->machine;
        }

        return ($this->machine = $machine);
    }

    public function name(?string $name = null): ?string
    {
        $this->name = $name ?? ($this->name);

        return $this->name;
    }

    public function version(?string $version = null): string
    {
        $this->version = $version ?? ($this->version);

        return $this->version;
    }

    public function space(?string $space = null): ?string
    {
        $this->space = $space ?? ($this->space);

        return $this->space;
    }

    public function writer(?string $writer = null): ?string
    {
        $this->writer = $writer ?? ($this->writer);

        return $this->writer;
    }

    public function addParameter(Parameter $parameter) {
        if ($parameter->objValidity() && !isset($this->parameters[$parameter->name()])) {
            $this->parameters[$parameter->name()] = $parameter->obj();
        }
    }

    public function parameters(?array $parameters = null): array
    {
        $this->parameters = $parameters ?? $this->parameters;

        return $this->parameters;
    }

    public function addExecution($execution) {
        $this->executions[] = $execution;
    }

    public function executions(?array $executions = null): array
    {
        $this->executions = $executions ?? $this->executions;

        return $this->executions;
    }
}
