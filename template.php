<?php

class Template {
	
	public static $TPL_PATH;
	public static $DEBUG = false;
	
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
			$p = new Template(file_get_contents($tpath), $values, $tplstack);
			$p->parse();
			return $p->getResult();
		}
		return '{? "'.$tplname.'" not found ?}';
	}
	
	private $tpl;
	private $values;
	private $cpos;
	private $res;
	private $printEnabled;
	private $tplstack;
	private $lastop;
	
	private function __construct($tpl, array $vals, array $tplstack){
		$this->tpl = $tpl;
		$this->values = $vals;
		$this->cpos = 0;
		$this->res = '';
		$this->printEnabled = true;
		$this->tplstack = $tplstack;
		$this->lastop = false;
	}
	
	public function getResult(){
		return $this->res;
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
			$this->res .= $str;
		}
	}
	
	private function appendAndMoveTo($pos, $stripl = false, $stripr = false){
		if ($pos > $this->cpos){
			$this->append(substr($this->tpl, $this->cpos, $pos - $this->cpos), $stripl, $stripr);
		}
		$this->cpos = $pos;
	}
	
	private function readValue($name){
		if ($name{0} == ':'){
			return substr($name, 1);
		} else if ($name{0} == '@'){
			switch (substr($name, 1)){
				case 'null': return null;
				case 'true': return true;
				case 'false': return false;
				default: return false;
			}
		} else {
			$v = $this->values;
			$fs = explode('|', $name);
			foreach (explode('.', $fs[0]) as $part){
				if (array_key_exists($part, $v)){
					$v = $v[$part];
				} else {
					$v = false;
					break;
				}
			}
			for ($i = 1; $i < count($fs); ++$i){
				$fargs = explode(' ', $fs[$i]);
				$facnt = count($fargs);
				switch ($fargs[0]){
					case 'safe': $v = htmlspecialchars($v); break;
					case 'lowercase': $v = mb_strtolower($v, 'utf-8'); break;
					case 'uppercase': $v = mb_strtoupper($v, 'utf-8'); break;
					case 'url': $v = urlencode($v);
					
					case 'substr':
						if ($facnt > 1){
							if ($facnt > 2){
								$v = substr($v, $fargs[1], $fargs[2]);
							} else {
								$v = substr($v, $fargs[1]);
							}
						}
						break;
				}
			}
			return $v;
		}
	}
	
	private function stripSpaces($str){
		return preg_replace('/^\s+|\s+$/', '', $str);
	}
	
	private function processStatement($stmt){
		$p = 0;
		if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
			$op = $matches[1][0];
			$p = $matches[0][1] + strlen($matches[0][0]);
			switch ($op){
				case 'if':
					if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
						$var1 = $matches[1][0];
						$p = $matches[0][1] + strlen($matches[0][0]);
						
						$pe = false;
						if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
							$op = $matches[1][0];
							$p = $matches[0][1] + strlen($matches[0][0]);
							
							if (preg_match('/[\s]*([^\s]+)/', $stmt, $matches, PREG_OFFSET_CAPTURE, $p) === 1){
								$var2 = $matches[1][0];
								$p = $matches[0][1] + strlen($matches[0][0]);
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
							} else {
								$pe = $this->readValue($var1) ? true : false;
							}
						} else {
							$pe = $this->readValue($var1) ? true : false;
						}
						
						$lpe = $this->printEnabled;
						$this->printEnabled &= $pe;
						if ($this->parse() == 'else'){
							$this->printEnabled = $lpe & !$pe;
							$this->parse();
						}
						$this->printEnabled = $lpe;
					}
					break;
					
				case 'for':
					$arr = preg_split('/\s+/', $stmt, -1, PREG_SPLIT_NO_EMPTY);
					if (count($arr) == 4 && $arr[2] == 'in'){
						$k = $arr[1];
						$a = $this->readValue($arr[3]);
						
						if (!is_array($a) || count($a) < 1){
							$lpe = $this->printEnabled;
							$this->printEnabled = false;
							$this->parse();
							$this->printEnabled = $lpe;
						} else {
							$pos = $this->cpos;
							$lastop = $this->lastop;
							foreach ($a as $key => $val){
								$this->values[$k] = $val;
								$this->cpos = $pos;
								$this->lastop = $lastop;
								$this->parse();
							}
						}
					}
					break;
					
				case 'include':
					$arr = preg_split('/\s+/', $stmt, -1, PREG_SPLIT_NO_EMPTY);
					$cnt = count($arr);
					if ($cnt >= 2){
						$tpl = $arr[1];
						$arg = $cnt > 2 ? $this->readValue($arr[2]) : $this->values;
						$this->append(self::build($tpl, $arg, $this->tplstack));
					}
					break;
			}
		}
	}
	
	public function parse(){
		while (preg_match('/{[{\?]/', $this->tpl, $matches, PREG_OFFSET_CAPTURE, $this->cpos) === 1){
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
					$this->append($this->readValue($stmt));
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
}

Template::init();

