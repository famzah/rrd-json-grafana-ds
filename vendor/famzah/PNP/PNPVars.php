<?php
namespace famzah\PNP;

class PNPVars {
	private $vars;

	public function __construct() {
		$this->vars = [];
	}

	private function assert_has($name) {
		if (!$this->has($name)) {
			throw new \Exception("Variable not declared yet: $name");
		}
	}

	public function has($name) {
		return array_key_exists($name, $this->vars);
	}

	public function set($name, $value) {
		$this->vars[$name] = $value;
	}

	public function &get($name) {
		$this->assert_has($name);
		return $this->vars[$name];
	}

	public function del($name) {
		$this->assert_has($name);
		unset($this->vars[$name]);
	}
}
