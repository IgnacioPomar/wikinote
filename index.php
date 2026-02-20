<?php
//Example Index file. You may, or even should, change in your own installation (overall, the configuration file path)

require_once __DIR__ . '/vendor/autoload.php';
use WikiNote\Launcher;
Launcher::main(__DIR__, 'cfg/siteCfg.php');
