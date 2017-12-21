<?php
spl_autoload_register(function ($class_name) {
	if (!preg_match('/^famzah\\\PNP\\\/', $class_name)) {
		echo "AUTO-LOAD requested not for our test namespace: $class_name\n";
		return;
	}

	//echo "AUTO-LOAD: $class_name\n";
	$class_name = preg_replace('/^famzah\\\PNP\\\/', '', $class_name);
	//echo "AUTO-LOAD2: $class_name\n";
	require_once("$class_name.php");
	//echo "LOAD OK\n";
});

use \famzah\PNP\PNPApp as PNPApp;

PNPApp::Initialize(null, []);
PNPApp::getInstance()->traceEnabled = true;

PNPApp::getInstance()->add_class('\my\testclass')
	->append_method('__construct', function ($me, $val1) {
		echo "__construct with val1=$val1\n\n";
		$me->objv1 = 99;
	})
	->append_method('test123', function ($me, $arg1) {
		echo "Hello test123 ($arg1)\n";
		if (isset($me->AAA)) {
			echo "Var AAA: ".$me->AAA."\n";
		}

		$me->objv1 -= 1;
		return 10 + $me->test2();
	})
	->append_method('test2', function ($me) {
		echo "Hello test2: " . $me->objv1 . "\n";
		return 47;
	})
; // add_class

$o = PNPApp::getInstance()->new_object('my\\\testclass\\\\', 'INIT-value');
$res = $o->test123('zzz');
echo "END result: $res\n\n";

PNPApp::getInstance()->add_class('\my\testclass')
	->append_method('test2', function ($me) {
		echo "I wrapped test2() in append mode\n";
		return 12;
	})
; // add_class

$res = $o->test123('zzz');
echo "END result: $res\n\n";

PNPApp::getInstance()->add_class('\my\testclass')
	->prepend_method('test123', function ($me) {
		echo "I wrapped test123() in prepend mode and changed its args + added var AAA\n";
		$args = $me->pnp->call_args;
		//print_r($args);
		$args[0] .= ' [+prepend!]';
		$me->pnp->setCallArgs($args);
		$me->AAA = 1;
	})
	->prepend_method('test2', function ($me) {
		echo "I wrapped test2() in prepend mode\n";
		//$me->pnp->skipNextCalls();
		return 1001;
	})
; // add_class

$res = $o->test123('zzz');
echo "END result: $res\n\n";

$o2 = PNPApp::getInstance()->new_object('my\\\testclass\\\\', 'INIT-value');
$res = $o2->test123('zzz');
echo "END result: $res\n\n";
