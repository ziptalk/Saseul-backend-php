<?php

namespace Saseul\Model;

class Request extends Code
{
    protected $response;

    public function __construct(array $initial_info = [])
    {
        $this->type = 'request';
        $this->name = $initial_info['name'] ?? '';
        $this->version = $initial_info['version'] ?? '1';
        $this->space = $initial_info['nonce'] ?? '';
        $this->writer = $initial_info['writer'] ?? '';

        $this->parameters = $initial_info['parameters'] ?? [];
        $this->executions = $initial_info['conditions'] ?? [];
        $this->response = $initial_info['response'] ?? [];
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
            'response' => $this->response,
        ];
    }

    public function response($response = null)
    {
        $this->response = $response ?? $this->response;

        return $this->response;
    }
}
