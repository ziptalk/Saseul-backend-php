<?php

namespace Saseul\Script;

use Core\Script;

class Version extends Script
{
    public $_description = "Display version info ";

    public function main()
    {
        $str = PHP_EOL. "    ____      _     ____   _____  _   _  _               ____      _    ";
        $str.= PHP_EOL. "   / ___|    / \   / ___| | ____|| | | || |      __   __|___ \    / |   ";
        $str.= PHP_EOL. "   \___ \   / _ \  \___ \ |  _|  | | | || |      \ \ / /  __) |   | |   ";
        $str.= PHP_EOL. "    ___) | / ___ \  ___) || |___ | |_| || |___    \ V /  / __/  _ | |   ";
        $str.= PHP_EOL. "   |____/ /_/   \_\|____/ |_____| \___/ |_____|    \_/  |_____|(_)|_|   ". PHP_EOL;
        $str.= PHP_EOL. "SASEUL Engine v". SASEUL_VERSION. " ";
        $str.= PHP_EOL. "  Copyright, 2019-2023, All rights reserved by ArtiFriends Inc. ";

        $this->print($str. PHP_EOL);
    }
}