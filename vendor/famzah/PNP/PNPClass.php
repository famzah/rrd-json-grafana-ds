<?php
namespace famzah\PNP;

class PNPClass {
	private $methods;

	private $app;
	private $ns;

	public function __construct(PNPApp $app, /* string */ $ns) {
		$this->app = $app;
		$this->ns = $ns;
		$this->methods = [];
	}

	public function prepend_method(/* string */ $name, \Closure $callable) {
		return $this->add_method('prepend', $name, $callable);
	}

	public function append_method(/* string */ $name, \Closure $callable) {
		return $this->add_method('append', $name, $callable);
	}

	private function add_method(/* string */ $order, /* string */ $name, \Closure $callable) {
		$this->app->assert_method_name($name);

		// prevent redefinition in the same class
		if (isset($this->methods[$name])) {
			throw new \Exception("Method already exists: $name");
		}
		//$this->methods[$name] = $callable;
		$this->methods[$name] = 1;

		$this->app->add_method($order, $this->ns, $name, $callable);

		return $this; // for easy chaining
	}
}
