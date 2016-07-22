<?php

require_once 'bbcode.php';

class Template {
	
	public static $TPL_PATH = './';
	public static $DEBUG = false;
	
	public static $OPS_CMP = ['=', '==', '===', '!==', '!=', '>', '>=', '<', '<='];
	
	private static $TPL_CACHE = [];
	private static $USER_FUNCS = [];
	
	public static function init($tplpath){
		self::$TPL_PATH = $tplpath;
	}
	
	public static function addUserFunctionHandler($f){
		if (is_callable($f)){
			self::$USER_FUNCS[] = $f;
		}
	}
	
	/**
	 * Вставляет в шаблон $tplname переменные из массива $values
	 * $tplname - имя шаблона
	 * $values - ассоциативный массив параметров вида array('arg' => 'val').
	 * В файле шаблона параметры обрамляются '{{ }}' (например '{{arg}}')
	 */
	public static function build($tplname, array $values, $tplstack = [], $blocks = []){
		$tpath = self::$TPL_PATH.$tplname.'.html';
		
		if (in_array($tpath, $tplstack)){
			return '{? recursion detected '.join($tplstack, ' => ').' ?}';
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
			$p->run($values, $tplstack, $blocks);
			if ($p->getError()){
				return $p->getError();
			}
			
			return $p->getResult();
		}
		return '{? "'.$tplname.'" not found ?}';
	}
	
	private $tpl;
	private $cpos;
	private $lastop;
	private $error;
	
	private $pgm;
	private $values;
	private $tplstack;
	private $blocks;
	private $res;
	
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
		$this->blocks = [];
	}
	
	public function getResult(){
		return $this->res;
	}
	
	public function getError(){
		return $this->error;
	}
	
	private function moveToEndOfTag($tag){
		if (($p = strpos($this->tpl, $tag, $this->cpos)) !== false){
			$this->cpos = $p + strlen($tag);
		} else {
			$this->cpos = strlen($this->tpl);
		}
	}
	
	private function append($str, $stripl = false, $stripr = false){
		if ($this->printEnabled){

			if ($stripl){
				$str = preg_replace("/^( |\t)*\n/", "\n", $str, 1);
			}

			if ($stripr){
				$str = preg_replace("/\n( |\t)*$/", '', $str, 1);
			}

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
	 * ['r', $value]
	 * ['l', [$n1, $n2, $n3...], [[$f1, [$arg1, $arg2...]], [f2, ...]]]
	 * ['b', $block, $arg]
	 * [$op, $args...] //suffixes: e - prEfix, i - Infix, p - Postfix
	 */
	public static function parseValue($name){
		if ($name{0} == ':'){
			return ['r', substr($name, 1)];
		} else if ($name{0} == '@'){
			if ($name == '@'){
				return ['l', [], []];
			}
			$v = false;
			switch (substr($name, 1)){
				case 'null': $v = null; break;
				case 'true': $v = true; break;
				case 'false': $v = false; break;
				default: $v = false; break;
			}
			return ['r', $v];
		} else if ($name{0} == '#'){
			$args = explode(' ', substr($name, 1));
			return ['b', $args[0], count($args) > 1 ? self::parseValue($args[1]) : null];
		}
		
		$val = ['l'];
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
	
	private function parseNextToken($stmt, &$p){
		if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
			$p = $matches[0][1] + strlen($matches[0][0]);
			return $matches[1][0];
		}
		return false;
	}
	
	private function processStatement($stmt){
		$p = 0;
		if (($op = $this->parseNextToken($stmt, $p)) !== false){
			switch ($op){
				case 'if':
					$pgm = ['if'];
					if (($tok = $this->parseNextToken($stmt, $p)) !== false){
						$pgm[] = $this->parseValue($tok);

						if (($tok = $this->parseNextToken($stmt, $p)) !== false){
							$pgm[0] = 'cmp';
							$pgm[] = $tok;
							
							if (!in_array($pgm[2], self::$OPS_CMP)){
								$this->setError('Bad compare operator in if');
								return;
							}
							
							if (($tok = $this->parseNextToken($stmt, $p)) !== false){
								$pgm[] = $this->parseValue($tok);
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
					if ($cnt >= 2){
						$pgm = ['incl', $arr[1]];
						if ($cnt == 2){
							$pgm[] = false;
							$pgm[] = null;
						}
						else if ($cnt == 3){
							$arg = $this->parseValue($arr[2]);
							if ($arg[0] == 'r' || $arg[0] == 'b'){
								$pgm[] = true;
								$pgm[] = [$arg];
							} else {
								$pgm[] = false;
								$pgm[] = $arg;
							}
						} else {
							$pgm[] = true;
							$args = [];
							for ($i = 2; $i < $cnt; ++$i){
								$args[] = $this->parseValue($arr[$i]);
							}
							$pgm[] = $args;
						}
						
						$this->pgm[] = $pgm;
					} else {
						$this->setError('Not enough parameters in include');
						return;
					}
					break;
					
				case 'block':
					$arr = preg_split('/\s+/', $stmt, -1, PREG_SPLIT_NO_EMPTY);
					$cnt = count($arr);
					if ($cnt == 2){
						$oldpgm = $this->pgm;
						$this->pgm = [];
						$this->parse();
						
						$this->blocks[$arr[1]] = $this->pgm;
						$this->pgm = $oldpgm;
					} else {
						$this->setError('Not enough parameters in block');
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
		$this->blocks = [];
		
		$this->parse();
		
		$this->tpl = '';
		
		return $this->error !== false;
	}
	
	private function mergeBlocks($blocks){
		foreach ($blocks as $n => $b){
			$this->blocks[$n] = $b;
		}
	}
	
	public function run($values, $tplstack, $blocks){
		if ($this->error){
			return false;
		}
		
		$myblocks = $this->blocks;
		
		$this->values = $values;
		$this->res = '';
		$this->tplstack = $tplstack;
		$this->mergeBlocks($blocks);
		
		$this->execPgm($this->pgm);
		
		$this->values = [];
		$this->tplstack = [];
		$this->blocks = $myblocks;
		
		return true;
	}
	
	/**
	 * Program arrays:
	 * ['str', $string]
	 * ['var', $var]
	 * ['if', $var, $body_true, $body_false]
	 * ['cmp', $var1, $op, $var2, $body_true, $body_false]
	 * ['incl', $tpl, $isarr, [$arg1, $arg2, ...]]
	 * ['for', $i, $var, $body]
	 * 
	 * Value arrays:
	 * ['r', $value]
	 * ['l', [$n1, $n2, $n3...], [[$f1, [$arg1, $arg2...]], [f2, ...]]]
	 * ['b', $block, $arg]
	 */
	
	private function readValue($op){
		$v = false;
		switch ($op[0]){
			case 'r':
				return $op[1];
	
			case 'l':
				$v = $this->values;
				foreach ($op[1] as $part){
					if (array_key_exists($part, $v)){
						$v = $v[$part];
					} else {
						$v = false;
						break;
					}
				}
				break;
				
			case 'b':
				$v = false;
				if (array_key_exists($op[1], $this->blocks)){
					$oldres = $this->res;
					$this->res = '';
					if ($op[2] !== null){
						$oldvals = $this->values;
						$this->values = $this->readValue($op[2]);
						$this->execPgm($this->blocks[$op[1]]);
						$this->values = $oldvals;
					} else {
						$this->execPgm($this->blocks[$op[1]]);
					}
					$v = $this->res;
					$this->res = $oldres;
				}
				return $v;
		}
		
		foreach ($op[2] as $func){
			$fargs = $func[1];
			$facnt = count($fargs);
			switch ($func[0]){
				case 'safe': $v = htmlspecialchars($v); break;
				case 'text': $v = str_replace(["\n", '  ', "\t"], ["\n<br>", ' &nbsp;', ' &nbsp; &nbsp;'], htmlspecialchars($v)); break;
				
				case 'lowercase': $v = mb_strtolower($v, 'utf-8'); break;
				case 'uppercase': $v = mb_strtoupper($v, 'utf-8'); break;
				
				case 'url': $v = htmlspecialchars($v); break;
				case 'urlparam': $v = urlencode($v); break;
				
				case 'json': $v = json_encode($v); break;
				
				case 'not': $v = !$v; break;
				
				case 'count': $v = count($v); break;
				
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
					if ($facnt > 1){
						if ($facnt > 2){
							$v = substr($v, $fargs[0], $fargs[1]);
						} else {
							$v = substr($v, $fargs[0]);
						}
					}
					break;
					
				case 'bbcode':
					$v = BBCode::process($v);
					break;
					
				default:
					foreach (self::$USER_FUNCS as $f){
						$v = $f($v, $func[0], $func[1]);
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
					$var1 = $this->readValue($ins[1]);
					$var2 = $this->readValue($ins[3]);
					$op = $ins[2];
					$pe = false;

					switch ($op){
						case '=':
						case '==': $pe = $var1 == $var2; break;
						case '===': $pe = $var1 === $var2; break;
						case '>': $pe = $var1 > $var2; break;
						case '<': $pe = $var1 < $var2; break;
						case '>=': $pe = $var1 >= $var2; break;
						case '<=': $pe = $var1 <= $var2; break;
						case '!=': $pe = $var1 != $var2; break;
						case '!==': $pe = $var1 !== $var2; break;
						default: $pe = $var1 ? true : false; break;
					}
					
					if ($pe){
						$this->execPgm($ins[4]);
					} else {
						$this->execPgm($ins[5]);
					}
					break;
				
				case 'incl':
					if ($ins[2]){
						$arg = [];
						foreach ($ins[3] as $insarg){
							$arg[] = $this->readValue($insarg);
						}
					}
					else if ($ins[3] !== null){
						$arg = $this->readValue($ins[3]);
					} else {
						$arg = $this->values;
					}
					
					if (!is_array($arg)){
						$arg = [$arg];
					}
					
					$this->res .= (self::build($ins[1], $arg, $this->tplstack, $this->blocks));
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

