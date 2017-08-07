<?php
/**
 * Author: maestroprog <maestroprog@gmail.com>
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class PipeFunctionsContainer {

	private $options;

	private $functions = [];

	public function __construct(){
		$methods = (new \ReflectionClass(static::class))->getMethods();
		foreach ($methods as $method){
			$methodName = $method->getShortName();
			if (substr($methodName, 0, 4) === 'eval' && $methodName !== 'eval'){
				$this->functions[$this->evalLowerCase(substr($methodName, 4))] = [$this, $methodName];
			}
		}
	}

	public function has(string $name): bool{
		return isset($this->functions[$name]);
	}

	public function add(string $name, callable $callback){
		$this->functions[$name] = $callback;
	}

	public function eval(string $name, $value, $args){
		if (!$this->has($name)){
			throw new \RuntimeException('Unknown pipe function "' . $name . '".');
		}
		return call_user_func_array($this->functions[$name], array_merge([$value], $args));
	}

	public function evalSafe($arg): string{
		return htmlspecialchars($arg);
	}

	public function evalUrl($arg): string{
		return $this->evalSafe($arg);
	}

	public function evalText($arg): string{
		return str_replace(
			["\n", '  ', "\t"],
			["\n<br>", '&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'],
			htmlspecialchars($arg)
		);
	}

	public function evalLowerCase($arg): string{
		return mb_strtolower($arg, 'utf-8');
	}

	public function evalUpperCase($arg): string{
		return mb_strtoupper($arg, 'utf-8');
	}

	public function evalUrlParam($arg): string{
		return rawurlencode($arg);
	}

	public function evalJson($arg): string{
		return json_encode($arg);
	}

	public function evalCount($arg): int{
		return count($arg);
	}

	public function evalIsArray($arg): bool{
		return is_array($arg);
	}

	public function evalKeys($arg){
		return array_keys($arg);
	}

	public function evalJoin($arg, $glue): string{
		return join($glue, $arg);
	}

	public function evalSplit($arg, $splitter){
		return explode($splitter, $arg);
	}

	public function evalSubStr($arg, ...$params): string{
		$count = count($params);
		if ($count >= 2){
			return substr($arg, $params[0], $params[1]);
		} elseif ($count === 1){
			return substr($arg, $params[0]);
		}
		throw new \InvalidArgumentException('Invalid parameters count.');
	}

	public function evalSlice($arg, ...$params){
		$count = count($params);
		if ($count >= 2){
			return array_slice($arg, $params[0], $params[1]);
		} elseif ($count === 1){
			return array_slice($arg, $params[0]);
		}
		throw new \InvalidArgumentException('Invalid parameters count.');
	}

	public function evalReplace($arg, ...$params): string{
		if (count($params) < 2){
			throw new \InvalidArgumentException('Invalid parameters count.');
		}
		return str_replace($params[0], $params[1], $arg);
	}

	public function evalDate($arg, ...$params): string{
		if (count($params) >= 1){
			$format = $params[0];
		} else{
			$format = TemplateEngine::instance()->getOptions()->getDateFormat();
		}
		$oldVal = $arg;
		if ($arg instanceof \DateTimeInterface){
			return $arg->format($format);
		}
		if (!is_numeric($arg)){
			$arg = strtotime($arg);
		}
		$arg = date($format, $arg);

		if (false === $arg){
			throw new \RuntimeException('Некорректное значение даты-времени: ' . $oldVal);
		}

		return $arg;
	}
}
