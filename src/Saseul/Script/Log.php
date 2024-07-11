<?php

namespace Saseul\Script;

use Core\Logger;
use Core\Script;
use Util\File;

class Log extends Script
{
    public $_description = "Display debug logs ";

    public function main()
    {
        $this->addOption('count', 'n', '<line>', 'Number of lines of logs to output (0: all)');
        $this->addOption('clear', 'c', '', 'Clears all logs. ');
        $this->addOption('follow', 'f', '', 'Output appended logs as the file grows ');

        $help = $this->_args['help'] ?? ($this->_args['h'] ?? null);
        $clear = $this->_args['clear'] ?? ($this->_args['c'] ?? null);
        $follow = $this->_args['follow'] ?? ($this->_args['f'] ?? null);
        $count = (int) ($this->_args['count'] ?? ($this->_args['n'] ?? 10));

        if (!is_null($help)) {
            $this->help();
        } elseif (!is_null($clear)) {
            File::overwrite(Logger::$log_file);
            $this->print(PHP_EOL. 'The log file has been cleared. '. PHP_EOL);
        } elseif (!is_null($follow)) {
            File::append(Logger::$log_file);

            $this->print(PHP_EOL. ' Press Ctrl + C to stop the log.'. PHP_EOL);

            while (true) {
                Logger::tail();
                usleep(100000);
            }
        } else {
            $this->print(PHP_EOL. 'Usage: saseul-script Log -- [args...] ');
            $this->print('  saseul-script Log --help '. PHP_EOL);

            Logger::tail($count);
            $this->print('');
        }
    }
}