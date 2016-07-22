<?php

class Template {
	
	public static $TPL_PATH;
	public static $DEBUG = false;
	
	public static $OPS_CMP = ['=', '==', '===', '!==', '!=', '>', '>=', '<', '<='];
	
	private static $TPL_CACHE = [];
	
	public static function init(){
		Template::$TPL_PATH = $_SERVER['DOCUMENT_ROOT'].'/res/tpl/';
	}
	
	/**
	 * Вставляет в шаблон $tplname переменные из массива $values
	 * $tplname - имя шаблона
	 * $values - ассоциативный массив параметров вида array('arg' => 'val').
	 * В файле шаблона параметры обрамляются '{{ }}' (например '{{arg}}')
	 */
	public static function build($tplname, array $values, $tplstack = []){
		$tpath = self::$TPL_PATH.$tplname.'.html';
		if (in_array($tpath, $tplstack)){
			return '{? recursion detected'.join($tplstack, ' => ').' ?}';
		}
		$tplstack[] = $tpath;
		if (file_exists($tpath)){
			$p = null;
			if (array_key_exists($tpath, self::$TPL_CACHE)){
				$p = self::$TPL_CACHE[$tpath];
			} else {
				$p = new Template();
				$p->compile(file_get_contents($tpath));
				self::$TPL_CACHE[$tpath] = $p;
			}
			$p->run($values, $tplstack);
			if ($p->getError()){
				return $p->getError();
			}
			return $p->getResult();
		}
		return '{? "'.$tplname.'" not found ?}';
	}
	
	private $tpl;
	private $values;
	private $cpos;
	private $res;
	private $tplstack;
	private $lastop;
	private $pgm;
	private $error;
	
	private function __construct(){
		$this->tpl = '';
		$this->values = null;
		$this->cpos = 0;
		$this->res = '';
		$this->printEnabled = true;
		$this->tplstack = null;
		$this->lastop = false;
		$this->pgm = [];
		$this->error = false;
	}
	
	public function getResult(){
		return $this->res;
	}
	
	public function getError(){
		return $this->error;
	}
	
	private function moveToEndOfTag($tag){
		if (($p = strpos($this->tpl, $tag, $this->cpos)) !== false){
			$this->cpos = $p + 2;
		} else {
			$this->cpos = strlen($this->tpl);
		}
	}
	
	private function append($str, $stripl = false, $stripr = false){
		if ($this->printEnabled){
//			if (self::$DEBUG){
//				echo '{? DEBUG: before('.($stripl ? 'true' : 'false').', '.($stripr ? 'true' : 'false').') ?}'.$str.'{? end ?}'."\n";
//			}
			if ($stripl){
				$str = preg_replace("/^( |\t)*\n/", "\n", $str, 1);
			}
//			if (self::$DEBUG){
//				echo '{? DEBUG: step ?}'.$str.'{? end ?}'."\n";
//			}
			if ($stripr){
				$str = preg_replace("/\n( |\t)*$/", '', $str, 1);
			}
//			if (self::$DEBUG){
//				echo '{? DEBUG: after ?}'.$str.'{? end ?}'."\n";
//			}
			$this->pgm[] = ['str', $str];
		}
	}
	
	private function appendAndMoveTo($pos, $stripl = false, $stripr = false){
		if ($pos > $this->cpos){
			$this->append(substr($this->tpl, $this->cpos, $pos - $this->cpos), $stripl, $stripr);
		}
		$this->cpos = $pos;
	}

	/**
	 * Value arrays:
	 * [true, $value]
	 * [false, [$n1, $n2, $n3...], [[$f1, [$arg1, $arg2...]], [f2, ...]]]
	 */
	public static function parseValue($name){
		if ($name{0} == ':'){
			return [true, substr($name, 1)];
		} else if ($name{0} == '@'){
			$v = false;
			switch (substr($name, 1)){
				case 'null': $v = null; break;
				case 'true': $v = true; break;
				case 'false': $v = false; break;
				default: $v = false; break;
			}
			return [true, $v];
		}
		
		$val = [false];
		$fs = explode('|', $name);
		$val[] = explode('.', $fs[0]);
		$val[] = [];
		
		for ($i = 1; $i < count($fs); ++$i){
			$fargs = explode(' ', $fs[$i]);
			$val[2][] = [$fargs[0], array_slice($fargs, 1)];
		}
		
		return $val;
	}
	
	private function stripSpaces($str){
		return preg_replace('/^\s+|\s+$/', '', $str);
	}
	
	private function setError($err){
		$this->error = $err;
	}
	
	private function processStatement($stmt){
		$p = 0;
		if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
			$op = $matches[1][0];
			$p = $matches[0][1] + strlen($matches[0][0]);
			switch ($op){
				case 'if':
					$pgm = ['if'];
					if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
						$pgm[] = $this->parseValue($matches[1][0]);
						$p = $matches[0][1] + strlen($matches[0][0]);

						if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
							$pgm[0] = 'cmp';
							$pgm[] = $matches[1][0];
							$p = $matches[0][1] + strlen($matches[0][0]);
							
							if (!in_array($pgm[2], self::$OPS_CMP)){
								$this->setError('Bad compare operator in if');
								return;
							}
							
							if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
								$pgm[] = $this->parseValue($matches[1][0]);
								$p = $matches[0][1] + strlen($matches[0][0]);
							} else {
								$this->setError('Not enough parameters in if');
								return;
							}
						}
						
						$oldpgm = $this->pgm;
						$this->pgm = [];
						if ($this->parse() == 'else'){
							$pgm[] = $this->pgm;
							$this->pgm = [];
							$this->parse();
							$pgm[] = $this->pgm;
						} else {
							$pgm[] = $this->pgm;
							$pgm[] = [];
						}
						$this->pgm = $oldpgm;
						$this->pgm[] = $pgm;
					}
					break;
					
				case 'for':
					$arr = preg_split('/\s+/', $stmt, -1, PREG_SPLIT_NO_EMPTY);
					if (count($arr) == 4 && $arr[2] == 'in'){
						$pgm = ['for', $arr[1], $this->parseValue($arr[3])];
						$oldpgm = $this->pgm;
						$this->pgm = [];
						$this->parse();
						
						$pgm[] = $this->pgm;
						$this->pgm = $oldpgm;
						$this->pgm[] = $pgm;
					} else {
						$this->setError('Not enough parameters or syntax error in for');
						return;
					}
					break;
					
				case 'include':
					$arr = preg_split('/\s+/', $stmt, -1, PREG_SPLIT_NO_EMPTY);
					$cnt = count($arr);
					if ($cnt == 2 || $cnt == 3){
						$pgm = ['incl', $arr[1], $cnt == 3 ? $this->parseValue($arr[2]) : null];
						$this->pgm[] = $pgm;
					} else {
						$this->setError('Not enough parameters in include');
						return;
					}
					break;
			}
		}
	}
	
	public function parse(){
		while (preg_match('/{[{\?]/', $this->tpl, $matches, PREG_OFFSET_CAPTURE, $this->cpos) === 1 && $this->error === false){
			$tag = $matches[0][0];
			$tpos = $matches[0][1];
			switch ($tag){
				case '{{':
					$this->appendAndMoveTo($tpos, $this->lastop, false);
					$this->lastop = false;
					$tpos += 2;
					$this->cpos = $tpos;
					$this->moveToEndOfTag('}}');
					$stmt = $this->stripSpaces(substr($this->tpl, $tpos, $this->cpos - $tpos - 2));
					$this->pgm[] = ['var', $this->parseValue($stmt)];
					break;
					
				case '{?':
					$this->appendAndMoveTo($tpos, $this->lastop, true);
					$this->lastop = true;
					$tpos += 2;
					$this->cpos = $tpos;
					$this->moveToEndOfTag('?}');
					$stmt = $this->stripSpaces(substr($this->tpl, $tpos, $this->cpos - $tpos - 2));
					if ($stmt == 'end' || $stmt == 'else'){
						return $stmt;
					}
					$this->processStatement($stmt);
					break;
			}
		}
		$this->appendAndMoveTo(strlen($this->tpl), $this->lastop, false);
		$this->lastop = false;
		return '';
	}
	
	public function compile($tpl){
		$this->error = false;
		$this->tpl = $tpl;
		$this->cpos = 0;
		$this->lastop = false;
		$this->pgm = [];
		
		$this->parse();
		
		$this->tpl = '';
		
		return $this->error !== false;
	}
	
	public function run($values, $tplstack){
		if ($this->error){
			return false;
		}
		$this->values = $values;
		$this->res = '';
		$this->tplstack = $tplstack;
		
		$this->execPgm($this->pgm);
		
		return true;
	}
	
	/**
	 * Program arrays:
	 * ['str', $string]
	 * ['var', $var]
	 * ['if', $var, $body_true, $body_false]
	 * ['cmp', $var1, $op, $var2, $body_true, $body_false]
	 * ['incl', $tpl, $args]
	 * ['for', $i, $var, $body]
	 * 
	 * Value arrays:
	 * [true, $value]
	 * [false, [$n1, $n2, $n3...], [[$f1, [$arg1, $arg2...]], [f2, ...]...]]
	 */
	
	private function readValue($op){
		if ($op[0]){
			return $op[1];
		}
	
		$v = $this->values;
		foreach ($op[1] as $part){
			if (array_key_exists($part, $v)){
				$v = $v[$part];
			} else {
				$v = false;
				break;
			}
		}
		
		foreach ($op[2] as $func){
			$fargs = $func[1];
			$facnt = count($fargs);
			switch ($func[0]){
				case 'safe': $v = htmlspecialchars($v); break;
				case 'lowercase': $v = mb_strtolower($v, 'utf-8'); break;
				case 'uppercase': $v = mb_strtoupper($v, 'utf-8'); break;
				case 'url': $v = urlencode($v);
				
				case 'substr':
					if ($facnt > 1){
						if ($facnt > 2){
							$v = substr($v, $fargs[0], $fargs[1]);
						} else {
							$v = substr($v, $fargs[0]);
						}
					}
					break;
			}
		}
		return $v;
	}
	
	private function execPgm($pgm){
		foreach ($pgm as $ins){
			switch ($ins[0]){
				case 'str':
					$this->res .= $ins[1];
					break;
					
				case 'var':
					$this->res .= $this->readValue($ins[1]);
					break;
					
				case 'if':
					if ($this->readValue($ins[1])){
						$this->execPgm($ins[2]);
					} else {
						$this->execPgm($ins[3]);
					}
					break;
					
				case 'cmp':
					$var1 = $ins[1];
					$var2 = $ins[3];
					$op = $ins[2];
					$pe = false;
					switch ($op){
						case '=':
						case '==': $pe = $this->readValue($var1) == $this->readValue($var2); break;
						case '===': $pe = $this->readValue($var1) === $this->readValue($var2); break;
						case '>': $pe = $this->readValue($var1) > $this->readValue($var2); break;
						case '<': $pe = $this->readValue($var1) < $this->readValue($var2); break;
						case '>=': $pe = $this->readValue($var1) >= $this->readValue($var2); break;
						case '<=': $pe = $this->readValue($var1) <= $this->readValue($var2); break;
						case '!=': $pe = $this->readValue($var1) != $this->readValue($var2); break;
						case '!==': $pe = $this->readValue($var1) !== $this->readValue($var2); break;
						default: $pe = $this->readValue($var1) ? true : false; break;
					}
					if ($pe){
						$this->execPgm($ins[4]);
					} else {
						$this->execPgm($ins[5]);
					}
					break;
				
				case 'incl':
					$arg = $ins[2] !== null ? $this->readValue($ins[2]) : $this->values;
					$this->res .= (self::build($ins[1], $arg, $this->tplstack));
					break;
				
				case 'for':
					$k = $ins[1];
					$a = $this->readValue($ins[2]);
					
					if (is_array($a) && count($a) > 0){
						foreach ($a as $key => $val){
							$this->values[$k] = $val;
							$this->execPgm($ins[3]);
						}
					}
					break;
			}
		}
	}
}

Template::init();

