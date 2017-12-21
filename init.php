<?php
function dump_backtrace_to_stderr($bt) {
	foreach ($bt as $id => $frame) {
		if ($id == 0) {
			file_put_contents('php://stderr', "Traceback (most recent call last):\n");
		}

		foreach (['type', 'class'] as $k) {
			if (!isset($frame[$k])) $frame[$k] = '';
		}
		file_put_contents('php://stderr', sprintf(
			'#%2d# %s%s%s()'."\n",
			$id, $frame['class'], $frame['type'], $frame['function']
		));
		if (isset($frame['file'])) {
			file_put_contents('php://stderr', sprintf(
				'     File "%s" (line %s)'."\n",
				$frame['file'], $frame['line']
			));
		}
	}
}

function dump_exception_to_stderr($e) {
	file_put_contents('php://stderr', sprintf("Exception: %s\n\n", $e->getMessage()));
	dump_backtrace_to_stderr($e->getTrace());
}

function debug_php_error_handler(int $errno, string $errstr, string $errfile, int $errline, array $errcontext) {
	file_put_contents('php://stderr', sprintf(
		"PHP warning triggered: %s (\"%s\" line %d)\n\n", $errstr, $errfile, $errline
	));
	dump_backtrace_to_stderr(array_reverse(debug_backtrace()));

	return false; // continue with the normal error handler
}

// shamelessly copied from WordPress source code :)
define('ABSPATH', dirname(__FILE__) . '/');

spl_autoload_register(function ($class_name) {
	$class_name = preg_replace('/\\\\/', '/', $class_name);
	if (strpos($class_name, '..') !== false) {
		throw new Exception("Bad class name: $class_name");
	}
	$sf = ABSPATH . "vendor/$class_name.php";
	if (file_exists($sf)) {
		require_once($sf);
	}
});

set_exception_handler('dump_exception_to_stderr');
set_error_handler('debug_php_error_handler');

require_once(ABSPATH . 'config.php');

\famzah\PNP\PNPApp::Initialize(ABSPATH . 'pnp', $config['plugins']);

#\famzah\PNP\PNPApp::getInstance()->traceEnabled = true; 

$Utils = \famzah\PNP\PNPApp::getInstance()->new_object('\rrdg\lib\Utils');
$Utils->x_check_config($config); // cross-check config values

$Mapping = \famzah\PNP\PNPApp::getInstance()
	->new_object('\rrdg\lib\Mapping', $config);

$Search = \famzah\PNP\PNPApp::getInstance()
	->new_object('\rrdg\lib\Search', $config, $Utils, $Mapping);
