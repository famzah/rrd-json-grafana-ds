<?php
require_once('init.php');

\famzah\PNP\PNPApp::getInstance()
	->new_object('\rrdg\controller\Search', $config, $Search, $Mapping, $Utils)
	->main();
