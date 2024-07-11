<?php

namespace Core;

use SplQueue;
use Util\Timer;

class Service
{
    protected $_args = [];
    protected $_routine = [];
    protected $_pointer = 0;
    protected $_queue;
    protected $_iterate = 0;

    public function args(?array $args = null): ?array
    {
        return $this->_args = $args ?? $this->_args;
    }

    public function init()
    {
    }

    public function main()
    {
        $this->taskRoutine();
        $this->taskQueue();
        $this->iterate();
    }

    public function iterate()
    {
        usleep($this->_iterate);
    }

    public function taskRoutine(): void
    {
        if (count($this->_routine) === 0) {
            return;
        }

        if ($this->_pointer >= count($this->_routine)) {
            $this->_pointer = 0;
        }

        $routine = $this->_routine[$this->_pointer];
        $this->_pointer++;

        $func = $routine['func'] ?? null;
        $timer = $routine['timer'] ?? null;
        $ucycle = $routine['ucycle'] ?? 1000000;

        if (is_callable($func) && $timer->lastInterval() > $ucycle) {
            call_user_func($func);
            $timer->check();
        }
    }

    public function taskQueue(): void
    {
        if (is_null($this->_queue)) {
            $this->_queue = new SplQueue();
        }

        if ($this->_queue->isEmpty()) {
            return;
        }

        $task = $this->_queue->dequeue();

        if (is_callable($task)) {
            call_user_func($task);
        }
    }

    public function addRoutine(callable $func, int $ucycle = 1000000) {
        $this->_routine[] = [
            'func' => $func,
            'timer' => new Timer(),
            'ucycle' => $ucycle
        ];
    }

    public function addQueue(callable $func)
    {
        if (is_null($this->_queue)) {
            $this->_queue = new SplQueue();
        }

        $this->_queue->enqueue($func);
    }
}
