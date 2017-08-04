<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class Template {
	public static $TPL_PATH = './';
	public static $CACHE_ENABLED = false;

	private static $TPL_CACHE = [];
	private static $USER_FUNCS = [];
	private static $GLOB_VARS = [];

	public $pgm;
	public $values;
	public $res;
	private $includes;
	private $blocks;
	private $widgets;

	private function __construct($pgm){
		$this->pgm = $pgm;
		$this->values = [];
		$this->res = '';
		$this->includes = [];
		$this->blocks = [];
		$this->widgets = [];
	}

	public static function init($tplpath, $cache = true){
		self::$TPL_PATH = $tplpath;
		self::$CACHE_ENABLED = $cache;
	}

	public static function addUserFunctionHandler($name, $f){
		if (isset(self::$USER_FUNCS[$name])) {
			throw new \RuntimeException(sprintf('Функция с именем "%s" уже была добавлена.', $name));
		}
		if (is_callable($f)){
			self::$USER_FUNCS[$name] = $f;
		}
	}

	public static function addGlobalVar($name, $val){
		self::$GLOB_VARS[$name] = $val;
	}

	/**
	 * Вставляет в шаблон $tplname переменные из массива $values
	 * $tplname - имя шаблона
	 * $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 */
	public static function build($tplname, array $values){
		$p = self::compile($tplname);
		if (is_string($p)){
			return $p;
		}
		$p->run($values);

		return $p->getResult();
	}

	private static function compile($tplname){
		$tpath = self::$TPL_PATH . $tplname . '.html';
		$tcpath = self::$TPL_PATH . $tplname . '.ctpl';

		if (self::$CACHE_ENABLED && file_exists($tcpath)){
			if (!file_exists($tpath) || filemtime($tcpath) >= filemtime($tpath)){
				$pgm = json_decode(file_get_contents($tcpath), true);
				if ($pgm !== false){
					$p = new Template($pgm);
					self::$TPL_CACHE[$tpath] = $p;

					return $p;
				}
			}
		}

		if (file_exists($tpath)){
			try {
				$p = null;
				if (array_key_exists($tpath, self::$TPL_CACHE)){
					$p = self::$TPL_CACHE[$tpath];
				} else {
					$c = new TemplateCompiler();
					$c->compile(file_get_contents($tpath));

					$pgm = $c->getProgram();
					if (self::$CACHE_ENABLED){
						file_put_contents($tcpath, json_encode($pgm));
					}

					$p = new Template($pgm);
					self::$TPL_CACHE[$tpath] = $p;
				}

				return $p;
			} catch (\Exception $e){
				return 'Error: ' . $tplname . '.html, ' . $e->getMessage();
			}
		}

		return 'Error: template "' . $tplname . '" not found';
	}

	public function run($values){
		$this->values = $values;
		$this->res = '';

		$this->execPgm($this->pgm);

		$this->values = [];
		$this->blocks = [];
		$this->widgets = [];

		return true;
	}

	public function execPgm($pgm){
		foreach ($pgm as $ins){
			switch ($ins[0]){
				case 'str':
					$this->res .= $ins[1];
					break;

				case 'var':
					$this->res .= $this->readValue($ins[1]);
					break;

				case 'calc':
					$this->readValue($ins[1]);
					break;

				case 'if':
					if ($this->readValue($ins[1])){
						$this->execPgm($ins[2]);
					} else {
						$this->execPgm($ins[3]);
					}
					break;

				case 'incl':
				case 'inclo':
					if ($ins[0] == 'inclo'){
						if (in_array($ins[1], $this->includes)){
							break;
						}
					}

					if ($ins[2]){
						$arg = [];
						foreach ($ins[3] as $insarg){
							$arg[] = $this->readValue($insarg);
						}
					} else if ($ins[3] !== null){
						$arg = $this->readValue($ins[3]);
					} else {
						$arg = $this->values;
					}

					if (!is_array($arg)){
						$arg = [$arg];
					}

					$p = self::compile($ins[1]);
					if (is_string($p)){
						$this->res .= $p;
					} else {
						$oldvals = $this->values;
						$this->values = $arg;
						$this->includes[] = $ins[1];

						$this->execPgm($p->pgm);

						$this->values = $oldvals;
					}
					break;

				case 'fore':
					$k = $ins[1];
					$a = $this->readValue($ins[2]);

					if (is_array($a) && count($a) > 0){
						foreach ($a as $key => $val){
							$this->values[$k] = $val;
							$this->execPgm($ins[3]);
						}
					}
					break;

				case 'for':
					$this->values[$ins[1]] = $this->readValue($ins[2]);
					while ($this->readValue($ins[3])){
						$this->execPgm($ins[5]);
						$this->readValue($ins[4]);
					}
					break;

				case 'regb':
					if (!array_key_exists($ins[1], $this->blocks)){
						$this->blocks[$ins[1]] = $ins[2];
					}
					break;

				case 'regw':
					$this->widgets[$ins[1]] = $ins[2];
					break;

				case 'widg':
					if (array_key_exists($ins[1], $this->widgets)){
						$attrs = $ins[2];
						foreach (array_keys($attrs) as $aname){
							$attrs[$aname] = $this->readValue($attrs[$aname]);
						}

						$oldwidgets = $this->widgets;
						$oldvals = $this->values;

						$this->values = [
							'attrs' => $attrs,
							'body' => new DelayedProgram($this, $ins[3], $oldvals),
						];
						$this->execPgm($this->widgets[$ins[1]]);

						$this->values = $oldvals;
						$this->widgets = $oldwidgets;
					} else {
						$this->res .= 'Error: widget ' . $ins[1] . ' not found';
					}
					break;
			}
		}
	}

	/**
	 * Program arrays:
	 * ['str', $string]
	 * ['var', $var]
	 * ['calc', $val]
	 * ['if', $var, $body_true, $body_false]
	 * ['incl', $tpl, $isarr, [$arg1, $arg2, ...]]
	 * ['inclo', $tpl, $isarr, [$arg1, $arg2, ...]]
	 * ['fore', $i, $var, $body]
	 * ['for', $i, $init, $cond, $post, $body]
	 * ['regb', $name, $body]
	 * ['regw', $name, $wbody]
	 * ['widg', $name, $attrs, $body]
	 *
	 * Value arrays:
	 * ['r', $value]
	 * ['l', $name]
	 * ['b', $block, $arg]
	 * ['g', $gvarname]
	 *
	 * [$op, $args...]
	 * ['|p', $val, $fname, [$arg1, $arg2, ...]]
	 * ['[p', $val, $key]
	 * ['(p', $func, [$arg1, $arg2, ...]]
	 */
	private function readValue($op){
		switch ($op[0]){
			case 'r':
				return $op[1];

			case 'l':
				if ($op[1] == 'this') return $this->values;

				return array_key_exists($op[1], $this->values) ? $this->values[$op[1]] : false;

			case 'g':
				if ($op[1] === null) return self::$GLOB_VARS;

				return array_key_exists($op[1], self::$GLOB_VARS) ? self::$GLOB_VARS[$op[1]] : false;

			case '[p':
				$v = $this->readValue($op[1]);
				$k = '' . $this->readValue($op[2]);

				if ($v === false){
					return false;
				} else if (is_array($v)){
					return array_key_exists($k, $v) ? $v[$k] : false;
				} else if (method_exists($v, $k)){
					return function () use ($v, $k){
						return call_user_func_array([$v, $k], func_get_args());
					};
				} else if (isset($v->$k)){
					return $v->$k;
				}

				return false;

			case '(p':
				$f = $this->readValue($op[1]);

				if (is_callable($f)){
					$args = [];
					foreach ($op[2] as $arg){
						$args[] = $this->readValue($arg);
					}

					return call_user_func_array($f, $args);
				}

				return false;

			case 'b':
				$v = false;
				if (array_key_exists($op[1], $this->blocks)){
					$oldres = $this->res;
					$this->res = '';
					if (count($op[2]) > 0){
						$nvals = [];
						foreach ($op[2] as $arg){
							$nvals[] = $this->readValue($arg);
						}

						if (count($nvals) == 1 && is_array($nvals[0])){
							$nvals = $nvals[0];
						}

						$oldvals = $this->values;
						$this->values = $nvals;
						$this->execPgm($this->blocks[$op[1]]);
						$this->values = $oldvals;
					} else {
						$this->execPgm($this->blocks[$op[1]]);
					}
					$v = $this->res;
					$this->res = $oldres;
				}

				return $v;

			case '|p':
				$v = $this->readValue($op[1]);
				$v = $this->applyFunction($v, $op[2], $op[3]);

				return $v;

			case '.i':
				if ($op[1][0] == 'l'){
					if ($op[1][1] == 'this'){
						$v1 = $this->values;
					} else if (array_key_exists($op[1][1], $this->values)){
						$v1 = $this->values[$op[1][1]];
					} else {
						$v1 = false;
					}
				} else {
					$v1 = $this->readValue($op[1]);
				}

				$v2 = $op[2][0] == 'l' ? $op[2][1] : '' . $this->readValue($op[2]);

				if ($v1 === false){
					return false;
				} else if (is_array($v1)){
					return array_key_exists($v2, $v1) ? $v1[$v2] : false;
				} else if (method_exists($v1, $v2)){
					return function () use ($v1, $v2){
						return call_user_func_array([$v1, $v2], func_get_args());
					};
				} else if (isset($v1->$v2)){
					return $v1->$v2;
				}

				return false;

			case '+e':
				return +$this->readValue($op[1]);
			case '-e':
				return -$this->readValue($op[1]);

			case '!e':
			case 'note':
				return !$this->readValue($op[1]);

			case '+i':
				$v1 = $this->readValue($op[1]);
				$v2 = $this->readValue($op[2]);
				if (is_string($v1)) return $v1 . $v2;
				else return $v1 + $v2;

			case '-i':
				return $this->readValue($op[1]) - $this->readValue($op[2]);
			case '*i':
				return $this->readValue($op[1]) * $this->readValue($op[2]);
			case '/i':
				return $this->readValue($op[1]) / $this->readValue($op[2]);

			case '==i':
				return $this->readValue($op[1]) == $this->readValue($op[2]);
			case '===i':
				return $this->readValue($op[1]) === $this->readValue($op[2]);
			case '!=i':
				return $this->readValue($op[1]) != $this->readValue($op[2]);
			case '!==i':
				return $this->readValue($op[1]) !== $this->readValue($op[2]);
			case '>i':
				return $this->readValue($op[1]) > $this->readValue($op[2]);
			case '<i':
				return $this->readValue($op[1]) < $this->readValue($op[2]);
			case '>=i':
				return $this->readValue($op[1]) >= $this->readValue($op[2]);
			case '<=i':
				return $this->readValue($op[1]) <= $this->readValue($op[2]);

			case 'andi':
				return $this->readValue($op[1]) && $this->readValue($op[2]);
			case 'ori':
				return $this->readValue($op[1]) || $this->readValue($op[2]);
			case 'xori':
				return $this->readValue($op[1]) ^ $this->readValue($op[2]);

			case '??i':
				return $this->readValue($op[1]) ?: $this->readValue($op[2]);

			case '=i':
			case '+=i':
			case '-=i':
			case '*=i':
			case '/=i':
				try {
					$v1 =& $this->readValueReference($op[1]);
				} catch (\Exception $e){
					return false;
				}
				$v2 = $this->readValue($op[2]);

				switch ($op[0]){
					case '+=i':
						if (is_string($v1)) $v1 .= $v2;
						else $v1 += $v2;
						break;

					case '=i':
						$v1 = $this->readValue($op[2]);
						break;
					case '-=i':
						$v1 -= $this->readValue($op[2]);
						break;
					case '*=i':
						$v1 *= $this->readValue($op[2]);
						break;
					case '/=i':
						$v1 /= $this->readValue($op[2]);
						break;
				}

				return $v1;

			default:
				return false;
		}

		return false;
	}

	private function applyFunction($v, $func, $fargs){
		$facnt = count($fargs);
		for ($i = 0; $i < $facnt; ++$i){
			$fargs[$i] = $this->readValue($fargs[$i]);
		}

		switch ($func){
			case 'safe':
				$v = htmlspecialchars($v);
				break;
			case 'text':
				$v = str_replace(["\n", '  ', "\t"], ["\n<br>", '&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'], htmlspecialchars($v));
				break;

			case 'lowercase':
				$v = mb_strtolower($v, 'utf-8');
				break;
			case 'uppercase':
				$v = mb_strtoupper($v, 'utf-8');
				break;

			case 'url':
				$v = htmlspecialchars($v);
				break;
			case 'urlparam':
				$v = rawurlencode($v);
				break;

			case 'json':
				$v = json_encode($v);
				break;

			case 'count':
				$v = count($v);
				break;

			case 'isarray':
				$v = is_array($v);
				break;
			case 'keys':
				$v = array_keys($v);
				break;

			case 'join':
				if ($facnt >= 1){
					$v = join($fargs[0], $v);
				}
				break;

			case 'split':
				if ($facnt >= 1){
					$v = explode($fargs[0], $v);
				}
				break;

			case 'substr':
				if ($facnt >= 1){
					if ($facnt >= 2){
						$v = substr($v, $fargs[0], $fargs[1]);
					} else {
						$v = substr($v, $fargs[0]);
					}
				}
				break;

			case 'slice':
				if ($facnt >= 1){
					if ($facnt >= 2){
						$v = array_slice($v, $fargs[0], $fargs[1]);
					} else {
						$v = array_slice($v, $fargs[0]);
					}
				}
				break;

			case 'replace':
				if ($facnt >= 2){
					$v = str_replace($fargs[0], $fargs[1], $v);
				}
				break;

			default:
				if (isset(self::$USER_FUNCS[$func])) {
					$v = self::$USER_FUNCS[$func]($v, $fargs);
				}
				break;
		}

		return $v;
	}

	private function &readValueReference($op){
		switch ($op[0]){
			case 'l':
				if ($op[1] == 'this')
					return $this->values;
				if (!array_key_exists($op[1], $this->values))
					$this->values[$op[1]] = false;

				return $this->values[$op[1]];

			case 'g':
				if ($op[1] === null)
					return self::$GLOB_VARS;
				if (!array_key_exists($op[1], self::$GLOB_VARS))
					self::$GLOB_VARS[$op[1]] = false;

				return self::$GLOB_VARS[$op[1]];

			case '[p':
				$v =& $this->readValueReference($op[1]);
				$k = '' . $this->readValue($op[2]);

				if (is_array($v)){
					if (!array_key_exists($k, $v))
						$v[$k] = false;

					return $v[$k];
				} else if (isset($v->$k)){
					return $v->$k;
				}

				break;

			case '.i':
				if ($op[1][0] == 'l'){
					if ($op[1][1] == 'this'){
						$v1 =& $this->values;
					} else if (array_key_exists($op[1][1], $this->values)){
						$v1 =& $this->values[$op[1][1]];
					} else {
						throw new \Exception();
					}
				} else {
					$v1 =& $this->readValueReference($op[1]);
				}

				$v2 = $op[2][0] == 'l' ? $op[2][1] : '' . $this->readValue($op[2]);

				if (is_array($v1)){
					if (!array_key_exists($v2, $v1))
						$v1[$v2] = false;

					return $v1[$v2];
				} else if (isset($v1->$v2)){
					return $v1->$v2;
				}

				break;
		}

		throw new \Exception();
	}

	public function getResult(){
		return $this->res;
	}

	public static function build_str($tplstr, array $values){
		$c = new TemplateCompiler();
		$c->compile($tplstr);
		$p = new Template($c->getProgram());
		$p->run($values);

		return $p->getResult();
	}
}
