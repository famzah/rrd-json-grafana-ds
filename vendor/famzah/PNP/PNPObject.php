<?php
namespace famzah\PNP;

class PNPObject {
	private $app;
	private $ns;
	private $methods;
	private $vars;

	public function __construct(PNPApp $app, /* string */ $ns, array &$methods, $args) {
		$this->app = $app;
		$this->ns = $ns;
		$this->methods = &$methods;
		$this->vars = new PNPVars();

		if ($this->pnp_method_exists('__construct')) {
			$this->call_pnp_method('__construct', $args);
		}
	}

	private function pnp_method_exists($name) {
		return isset($this->methods[$name]);
	}

	private function call_pnp_method($name, $orig_args) {
		if (!$this->pnp_method_exists($name)) {
			throw new \BadMethodCallException("Method does not exist: $name");
		}

		$this->app->callLevel++;

		$prev_return = null;
		$last_args = $orig_args;
		foreach ($this->methods[$name] as $_id => $method_data) {
			$id = $_id + 1;
			$CallCtx = new PNPObjectCallCtx(
				$this, $this->ns, $id, $prev_return, true, $last_args
			);
			$CallThis = new PNPObjectCtxProxy($this, $CallCtx);

			if ($this->app->traceEnabled) {
				file_put_contents('php://stderr', sprintf(
					'PNPApp_CallTrace[%d]: %s%s->%s() #%d/%d @ %s'."\n",
					getmypid(),
					str_repeat(' ', ($this->app->callLevel-1)*4),
					$this->ns, $name,
					$id, count($this->methods[$name]),
					$method_data['origin']
				));
			}

			$call_result = call_user_func_array($method_data['callable'], array_merge(
				[$CallThis], $CallCtx->call_args
			));

			$prev_return = $call_result;
			$last_args = $CallCtx->call_args;

			if (!$CallCtx->call_next) {
				break;
			}
		}

		$this->app->callLevel--;
		if ($this->app->callLevel < 0) {
			trigger_error("this->app->callLevel is negative", E_USER_WARNING);
			$this->app->callLevel = 0;
		}

		return $call_result;
	}

	public function __call($name, $args) {
		return $this->call_pnp_method($name, $args);
	}

	public function __set($name, $value) {
		$this->vars->set($name, $value);
	}

	public function &__get($name) {
		return $this->vars->get($name);
	}

	public function __isset($name) {
		return $this->vars->has($name);
	}

	public function __unset($name) {
		$this->vars->del($name);
	}
}

class PNPObjectCallCtx {
	private $Main;
	private $ns;
	private $call_id;
	private $prev_return;
	private $do_call_next;
	private $call_args;

	public function __construct(PNPObject $Main, $ns, $call_id, $prev_return, $call_next, $call_args) {
		$this->Main = $Main;
		$this->ns = $ns;
		$this->call_id = $call_id;
		$this->prev_return = $prev_return;
		$this->call_next = $call_next;
		$this->call_args = $call_args;
	}

	public function &__get($name) { // read-only
		return $this->{$name};
	}

	public function skipNextCalls() { // should we call the next method in the chain
		$this->call_next = false;
	}

	public function setCallArgs($new_args) {
		$this->call_args = $new_args;
	}

	public function setRefVar($name, $value) {
	}
}

class PNPObjectCtxProxy {
	private $Main;
	private $CallCtx;

	public function __construct(PNPObject $Main, PNPObjectCallCtx $CallCtx) {
		$this->Main = $Main;
		$this->CallCtx = $CallCtx;
	}

	public function __call($name, $args) {
		return call_user_func_array([$this->Main, $name], $args); 
	}

	private function protect_read_only_properties($name) {
		if ($name == 'pnp') {
			throw new \Exception('Property "pnp" is read-only');
		}
	}

	public function __set($name, $value) {
		$this->protect_read_only_properties($name);
		$this->Main->{$name} = $value;
	}

	public function &__get($name) {
		if ($name == 'pnp') {
			return $this->CallCtx;
		}
		$ret = &$this->Main->{$name}; # PHP 5.6 compatibility
		return $ret;
	}

	public function __isset($name) {
		if ($name == 'pnp') {
			return true;
		}
		return isset($this->Main->{$name});
	}

	public function __unset($name) {
		$this->protect_read_only_properties($name);
		if (!isset($this->Main->{$name})) {
			throw new \InvalidArgumentException(sprintf(
				'Property "%s" is not set; you cannot unset() it',
				$name
			));
		}
		unset($this->Main->{$name});
	}
}
