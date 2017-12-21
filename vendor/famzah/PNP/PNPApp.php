<?php
namespace famzah\PNP;

class PNPApp {
	private $methods = [];
	private $pnp_dir;
	private $plugins;
	public $traceEnabled;
	public $callLevel = 0; // used when debug tracing
	//public $registry;

	private static $app_instance = null;

	private function __construct($pnp_dir, $plugins) {
		$this->pnp_dir = $pnp_dir;
		$this->plugins = $plugins;
		$this->traceEnabled = false;
		//$this->registry = new PNPVars();

		foreach ($this->plugins as $plugin) {
			if (strpos($plugin, '..') !== false) {
				throw new \Exception("Plugin names cannot contain '..': $plugin");
			}
		}
	}

	public static function Initialize($pnp_dir, $plugins) {
		if (self::$app_instance) {
			throw new \Exception("PNPApp can be initialized only once");
		}

		self::$app_instance = new PNPApp($pnp_dir, $plugins);
	}

	public static function getInstance() {
		if (!self::$app_instance) {
			throw new \Exception("PNPApp is not initialized yet");
		}
		return self::$app_instance;
	}

	public function assert_method_name(string $name) {
		if (strpos($name, '\\') !== false || strpos($name, '..') !== false) {
			throw new \Exception("Method names cannot contain a backslash or '..': $name");
		}
	}

	private function normalize_ns(string $ns) {
		if (!preg_match('/[a-z0-9]/i', $ns)) {
			throw new \Exception("Namespace must contain at least one letter or digit character");
		}
		$ns = preg_replace('/\\\\+/', '\\', $ns);
		if (substr($ns, 0, 1) != '\\') { // does not begin with \
			$ns = '\\'.$ns;
		}
		if (substr($ns, -1, 1) == '\\') { // ends with slash
			$ns = substr($ns, 0, -1);
		}

		if (strpos($ns, '..') !== false) {
			throw new \Exception("Namespace cannot contain '..': $ns");
		}

		return $ns;
	}

	public function add_class(string $ns) {
		return new PNPClass($this, $ns);
	}

	private function load_source_file(string $ns) {
		foreach ($this->plugins as $plugin) {
			$sf = sprintf('%s/%s/%s.php',
				$this->pnp_dir,
				$plugin,
				preg_replace('/\\\\/', '/', $ns)
			);
			if (file_exists($sf)) {
				require_once($sf);
			}
		}
	}

	public function new_object(string $ns, ...$args) {
		$ns = $this->normalize_ns($ns);
		$this->load_source_file($ns);
		if (!isset($this->methods[$ns])) {
			throw new \Exception("Method namespace does not exist: $ns");
		}
		return new PNPObject($this, $ns, $this->methods[$ns], $args);
	}

	public function add_method(string $order, string $ns, string $name, \Closure $callable) {
		$this->assert_method_name($name);
		$ns = $this->normalize_ns($ns);

		if (!isset($this->methods[$ns])) {
			$this->methods[$ns] = [];
		}
		if (!isset($this->methods[$ns][$name])) {
			$this->methods[$ns][$name] = [];
		}

		$bt = debug_backtrace(0);
		array_shift($bt);
		array_shift($bt);
		$mvalue = [
			'origin' => sprintf('"%s" (line %d)', $bt[0]['file'], $bt[0]['line']),
			'callable' => $callable,
		];
		switch ($order) {
			case 'append':
				$this->methods[$ns][$name][] = $mvalue;
				break;
			case 'prepend':
				array_unshift($this->methods[$ns][$name], $mvalue);
				break;
			default:
				throw new \Exception("Unknown order: $order");
				break;
		}
	}
}

