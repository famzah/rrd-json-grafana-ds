<?php
require_once('init.php');

$CachingRRD = \famzah\PNP\PNPApp::getInstance()
	->new_object('\rrdg\lib\CachingRRD');

\famzah\PNP\PNPApp::getInstance()
	->new_object('\rrdg\controller\Query', $config, $Search, $Mapping, $Utils, $CachingRRD)
	->main();
