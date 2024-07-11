<?php

namespace Saseul;

use Saseul\Script\Start;
use Saseul\Script\Stop;
use Saseul\Staff\ProcessManager;

trait ScriptTool
{
    public function restart(callable $func)
    {
        $is_running = ProcessManager::isRunning(ProcessManager::MASTER);

        if ($is_running) {
            $stop = new Stop();
            $stop->main();
        }

        call_user_func($func);

        if ($is_running) {
            $start = new Start();
            $start->main();
        }
    }
}
