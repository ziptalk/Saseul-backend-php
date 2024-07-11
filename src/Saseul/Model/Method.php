<?php

namespace Saseul\Model;

class Method extends Code
{
    public function __construct(array $initial_info = [])
    {
        $this->type = $initial_info['t'] ?? 'request';
        $this->machine = $initial_info['m'] ?? '0.2.0';
        $this->name = $initial_info['n'] ?? '';
        $this->version = $initial_info['v'] ?? '1';
        $this->space = $initial_info['s'] ?? '';
        $this->writer = $initial_info['w'] ?? '';
        $this->parameters = $initial_info['p'] ?? [];
        $this->executions = $initial_info['e'] ?? [];
    }

    public function compile(): array
    {
        return [
            't' => $this->type,
            'm' => $this->machine,
            'n' => $this->name,
            'v' => $this->version,
            's' => $this->space,
            'w' => $this->writer,
            'p' => $this->parameters,
            'e' => $this->executions,
        ];
    }

    public function json(): string
    {
        return json_encode($this->compile());
    }
}
