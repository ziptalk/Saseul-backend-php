<?php
/**
 * Legacy codes.
 */

namespace Saseul\Model;

class Contract extends Code
{
    protected $updates;

    public function __construct(array $initial_info = [])
    {
        $this->type = 'contract';
        $this->version = $initial_info['version'] ?? '1';
        $this->name = $initial_info['name'] ?? '';
        $this->space = $initial_info['nonce'] ?? '';
        $this->writer = $initial_info['writer'] ?? '';

        $this->parameters = $initial_info['parameters'] ?? [];
        $this->executions = $initial_info['conditions'] ?? [];
        $this->updates = $initial_info['updates'] ?? [];
    }

    public function compile(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'version' => $this->version,
            'nonce' => $this->space,
            'writer' => $this->writer,
            'parameters' => $this->parameters,
            'conditions' => $this->executions,
            'updates' => $this->updates,
        ];
    }

    public function addUpdate($update)
    {
        $this->updates[] = $update;
    }

    public function updates(?array $updates = null)
    {
        $this->updates = $updates ?? $this->updates;

        return $this->updates;
    }
}
