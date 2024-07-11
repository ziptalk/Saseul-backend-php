<?php

namespace Core;

use Util\Parser;

class Script
{
    public $_description = '';
    public $_options = [];

    protected $_args = [];

    public function args(?array $args = null): ?array
    {
        return $this->_args = $args ?? $this->_args;
    }

    public function print($obj): void
    {
        print_r(Parser::alignedString($obj). PHP_EOL);
    }

    public function ask(string $msg = ''): string
    {
        if ($msg !== '') {
            print_r(PHP_EOL . $msg. PHP_EOL);
        }

        return trim(fgets(STDIN));
    }

    public function main()
    {
    }

    public function addOption(string $key, string $short = '', string $example = '', string $description = '') {
        $this->_options[$key] = [ 'short' => $short, 'example' => $example, 'description' => $description ];
    }

    public function help()
    {
        $str = '';
        $base = basename($_SERVER['SCRIPT_NAME']);
        $script = preg_replace('/^(.+)\\\\/', '', get_called_class());
        $maxlength = 0;
        $help = $this->_options['help'] ?? null;

        if (is_null($help)) {
            $this->addOption('help', 'h', '', 'This help');
        }

        # check length;
        foreach ($this->_options as $key => $value) {
            (is_null($value['short']) || $value['short'] === '') ? $short_string = '' : $short_string = "-{$value['short']}";
            $example = $value['example'] ?? '';
            $maxlength = max($maxlength, strlen("  $short_string --$key $example "));
        }

        $str.= PHP_EOL. "Usage: $base $script -- [args...]". PHP_EOL;

        # first option;
        foreach ($this->_options as $key => $value) {
            $example = $value['example'] ?? '';
            $str.= "  $base $script --$key $example ". PHP_EOL;
            break;
        }

        $str.= PHP_EOL. "Options: ". PHP_EOL;

        # full options;
        foreach ($this->_options as $key => $value) {
            (is_null($value['short']) || $value['short'] === '') ? $short_string = '' : $short_string = "-{$value['short']}";
            $example = $value['example'] ?? '';
            $description = $value['description'] ?? '';
            $prefix = str_pad("  $short_string --$key $example ", $maxlength, ' ', STR_PAD_RIGHT);
            $str.= "$prefix $description". PHP_EOL;
        }

        $str.= PHP_EOL. $this->_description. PHP_EOL;
        $this->print($str);
    }
}
