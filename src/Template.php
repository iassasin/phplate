<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class Template {
	const AUTOSAFE_IGNORE = ['safe', 'text', 'raw', 'url'];

	private $path;
	public $pgm;
	public $values;
	public $res;
	private $includes;
	private $blocks;
	private $widgets;
	private $globalVars;

	public static function init($tplPath, TemplateOptions $options = null): TemplateEngine {
		return TemplateEngine::init($tplPath, $options ?: new TemplateOptions());
	}

	/**
	 * Вставляет в шаблон $tplName переменные из массива $values
	 * @param string $tplName - имя шаблона
	 * @param array $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 * @return string
	 */
	public static function build(string $tplName, array $values): string {
		return TemplateEngine::instance()->build($tplName, $values);
	}

	/**
	 * Вставляет в шаблон $tplStr переменные из массива $values
	 * @param string $tplStr - код шаблона
	 * @param array $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 * @return string
	 */
	public static function buildStr(string $tplStr, array $values): string {
		return TemplateEngine::instance()->buildStr($tplStr, $values);
	}

	/**
	 * Вставляет в шаблон $tplPath переменные из массива $values
	 * @param string $tplPath - путь к файлу шаблона
	 * @param array $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 * @return string
	 */
	public static function buildFile(string $tplPath, array $values): string {
		return TemplateEngine::instance()->buildFile($tplPath, $values);
	}

	public static function addUserFunctionHandler(string $name, callable $f){
		TemplateEngine::instance()->addUserFunctionHandler($name, $f);
	}

	public static function addGlobalVar($name, $val){
		TemplateEngine::instance()->addGlobalVar($name, $val);
	}

	public function __construct(string $path, $pgm, $globalVars){
		$this->path = $path;
		$this->pgm = $pgm;
		$this->values = [];
		$this->res = '';
		$this->includes = [];
		$this->blocks = [];
		$this->widgets = [];
		$this->globalVars = $globalVars;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function run($values): self {
		$this->values = $values;
		$this->res = '';

		$this->execPgm($this->pgm);

		$this->values = [];
		$this->blocks = [];
		$this->widgets = [];

		return $this;
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

					$p = TemplateEngine::instance()->compile($ins[1], $this->path);
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
					} else {
						$this->execPgm($ins[4]);
					}
					break;

				case 'for':
					$this->values[$ins[1]] = $this->readValue($ins[2]);
					if ($this->readValue($ins[3])){
						do {
							$this->execPgm($ins[5]);
							$this->readValue($ins[4]);
						}
						while ($this->readValue($ins[3]));
					} else {
						$this->execPgm($ins[6]);
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

	public function getResult(){
		return $this->res;
	}


	/**
	 * Program arrays:
	 * ['str', $string]
	 * ['var', $var]
	 * ['calc', $val]
	 * ['if', $var, $body_true, $body_false]
	 * ['incl', $tpl, $isarr, [$arg1, $arg2, ...]]
	 * ['inclo', $tpl, $isarr, [$arg1, $arg2, ...]]
	 * ['fore', $i, $var, $body, $elsebody]
	 * ['for', $i, $init, $cond, $post, $body, $elsebody]
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
	 * ['[e', [$el1, $el2, ...]]
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
				if ($op[1] === null) return $this->globalVars;

				return array_key_exists($op[1], $this->globalVars) ? $this->globalVars[$op[1]] : false;

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

			case '[e':
				$vals = [];
				foreach ($op[1] as $key => $val){
					$vals[$key] = $this->readValue($val);
				}

				return $vals;

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
				try{
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
	}

	private function applyFunction($v, $func, $fargs){
		$facnt = count($fargs);
		for ($i = 0; $i < $facnt; ++$i){
			$fargs[$i] = $this->readValue($fargs[$i]);
		}
		return TemplateEngine::instance()->getUserFunctions()->eval($func, $v, $fargs);
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
				if ($op[1] === null){
					return $this->globalVars;
				}
				if (!array_key_exists($op[1], $this->globalVars)){
					$this->globalVars[$op[1]] = false;
				}

				return $this->globalVars[$op[1]];

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
}
