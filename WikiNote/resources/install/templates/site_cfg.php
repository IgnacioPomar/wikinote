<?php
/**
 * Site CFG
 */

// site Version
$GLOBALS ['Version'] = '@@siteVersion@@';

// Options
$GLOBALS ['authRecover'] = @@authRecover@@;
$GLOBALS ['authLog'] = @@authLog@@;
$GLOBALS ['menuType'] = '@@menuType@@';

// ----------------------------------
// Database CFG
$GLOBALS ['dbserver'] = '@@dbserver@@';
$GLOBALS ['dbport'] = '@@dbport@@';
$GLOBALS ['dbuser'] = '@@dbuser@@';
$GLOBALS ['dbpass'] = '@@dbpass@@';
$GLOBALS ['dbname'] = '@@dbname@@';

// ----------------------------------


$GLOBALS ['skin'] = '@@skins@@';

// ----------------------------------
define ('SERVER_ENCODING', 'UTF-8');
date_default_timezone_set ('Europe/Madrid');
setlocale (LC_ALL, 'es_ES.UTF8');
