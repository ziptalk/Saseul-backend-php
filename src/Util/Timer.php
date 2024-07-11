<?php

namespace Util;

class Timer
{
    protected $s = 0;
    protected $m = 0;

    public function __construct($autostart = true)
    {
        if ($autostart) {
            $this->start();
        }
    }

    public function start(): int
    {
        $this->s = Clock::utime();
        $this->m = $this->s;

        return $this->s;
    }

    public function check(): int
    {
        $l = Clock::utime();
        $i = $l - $this->m;
        $this->m = $l;

        return $i;
    }

    public function log(): void
    {
        print_r(self::check(). PHP_EOL);
    }

    public function checkedInterval(): int
    {
        return ($this->m - $this->s);
    }

    public function lastInterval(): int
    {
        return (Clock::utime() - $this->m);
    }

    public function fullInterval(): int
    {
        return (Clock::utime() - $this->s);
    }
}
