<?php

require_once 'bbcode.php';

$PDEBUG = false;

function DEBUG($str){
	global $PDEBUG;
	if ($PDEBUG)
		echo $str."\n<br>";
}

class TemplateLexer {
	private $input;
	private $ilen;
	public $toktype;
	public $token;
	private $cpos;
	private $cline;
	
	const TOK_NONE = 0;
	const TOK_ID = 1;
	const TOK_OP = 2;
	const TOK_STR = 3;
	const TOK_NUM = 4;
	const TOK_UNK = 10;
	
	const OPERATORS = '+-*/|&.,@#$!?:;~%^=<>()[]';
	const TERMINAL_OPERATORS = '.,@#;()[]';
	const ID_OPERATORS = ['and', 'or', 'xor'];
	
	private static $PRE_OPS;
	private static $INF_OPS;
	private static $POST_OPS;
	
	public static function _init(){
		self::$PRE_OPS = [
			7 => ['+', '-', '!'],
		];
		
		self::$INF_OPS = [
			1 => ['or', 'xor'],
			2 => ['and'],
			3 => ['==', '===', '!=', '!==', '>=', '<=', '<', '>'],
			4 => ['+', '-'],
			5 => ['*', '/'],
			6 => ['^'],
			
			8 => ['.'],
		];
		
		self::$POST_OPS = [
			7 => [
				'|' => function($parser, $val, $lvl){
					DEBUG('+ operator_|p_call');
					if (!$parser->nextToken() || $parser->toktype != self::TOK_ID){
						$parser->error('Function name excepted in "|"');
						return null;
					}
					
					$fname = $parser->token;
					$args = [];
					
					if ($parser->nextToken()){
						if ($parser->toktype == self::TOK_OP && $parser->token == '('){
							do {
								$parser->nextToken();
								$args[] = $parser->infix(1);
							} while ($parser->toktype == self::TOK_OP && $parser->token == ',');
							
							if ($parser->toktype != self::TOK_OP || $parser->token != ')'){
								$parser->error('Excepted ")" in "|"');
								return null;
							}
							
							$parser->nextToken();
						}
					}
					
					DEBUG('- operator_|p_end');
					return ['|p', $val, $fname, $args];
				},
				
				'[' => function($parser, $val, $lvl){
					DEBUG('+ operator_[p_call');
					
					if (!$parser->nextToken()){
						$parser->error('Argument excepted in "["');
						return null;
					}
					
					$arg = $parser->infix(1);
					
					if ($parser->toktype != self::TOK_OP || $parser->token != ']'){
						$parser->error('Excepted "]"');
						return null;
					}
					
					$parser->nextToken();
					
					DEBUG('- operator_[p_end');
					return ['[p', $val, $arg];
				},
			],
		];
	}
	
	public function __construct(){
		$this->toktype = self::TOK_NONE;
		$this->token = '';
	}
	
	public function setInput($str){
		$this->input = $str;
		$this->ilen = strlen($str);
		$this->cpos = 0;
		$this->cline = 1;
	}
	
	public function error($msg){
		throw new Exception('input, '.$this->cline.': '.$msg);
	}
	
	public function nextToken(){
		$res = $this->nextToken_();
		DEBUG('Token: ['.$this->toktype.', "'.$this->token.'"]');
		return $res;
	}
	
	public function nextToken_(){
		$cpos = $this->cpos;
		
		while ($cpos < $this->ilen && strpos("\n\t ", $this->input{$cpos}) !== false){
			if ($this->input{$cpos} == "\n")
				++$this->cline;
			++$cpos;
		}
		$this->cpos = $cpos;

		if ($cpos >= $this->ilen){
			$this->token = '';
			$this->toktype = self::TOK_NONE;
			return false;
		}
		
		$ch = $this->input{$cpos};
		
		if ($ch == '"' || $ch == "'"){
			$esym = $ch;
			++$cpos;
			$this->cpos = $cpos;
			
			while ($cpos < $this->ilen){
				$ch = $this->input{$cpos};
				if ($ch != $esym){
					//todo escape symbols
					++$cpos;
				} else {
					break;
				}
			}
			
			if ($cpos >= $this->ilen || $this->input{$cpos} != $esym){
				//error
			}
			
			$this->toktype = self::TOK_STR;
			$this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
			$this->cpos = $cpos + 1;
			
			return true;
		}
		else if (strpos(self::OPERATORS, $ch) !== false){
			$this->toktype = self::TOK_OP;
			++$cpos;
			
			if (strpos(self::TERMINAL_OPERATORS, $ch) === false){
				while ($cpos < $this->ilen && strpos(self::OPERATORS, $this->input{$cpos}) !== false && strpos(self::TERMINAL_OPERATORS, $this->input{$cpos}) === false)
					++$cpos;
			}
			
			$this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
			$this->cpos = $cpos;
			
			return true;
		}
		else if ($ch >= '0' && $ch <= '9'){
			$this->toktype = self::TOK_NUM;
			
			++$cpos;
			while ($cpos < $this->ilen){
				$ch = $this->input{$cpos};
				if ($ch >= '0' && $ch <= '9' || $ch == '.'){
					++$cpos;
				} else {
					break;
				}
			}
			
			$this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
			$this->cpos = $cpos;
			
			return true;
		}
		else if ($ch >= 'a' && $ch <= 'z' || $ch >= 'A' && $ch <= 'Z' || $ch == '_') {
			$this->toktype = self::TOK_ID;
			
			++$cpos;
			while ($cpos < $this->ilen){
				$ch = $this->input{$cpos};
				if ($ch >= 'a' && $ch <= 'z' || $ch >= 'A' && $ch <= 'Z' || $ch >= '0' && $ch <= '9' || $ch == '_'){
					++$cpos;
				} else {
					break;
				}
			}
			
			$this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
			$this->cpos = $cpos;
			
			if (in_array($this->token, self::ID_OPERATORS)){
				$this->toktype = self::TOK_OP;
			}
			
			return true;
		}
		else {
			$this->toktype = self::TOK_UNK;
			$this->token = $ch;
			$this->cpos = $cpos + 1;
			return true;
		}
	}
	
	public function getToken(){
		return [$this->toktype, $this->token];
	}
	
	public function parseExpression(){
		return $this->infix(1);
	}
	
	public function findOperator($ops, $lvl, $op){
		DEBUG('search operator: '.$op.' '.$lvl);
		foreach ($ops as $level => $lops){
			if ($level >= $lvl){
				foreach ($lops as $opk => $opv){
					if (is_callable($opv)){
						if ($opk == $op)
							return [$level, $opv];
					} else if ($opv == $op){
						return [$level, $opv];
					}
				}
			}
		}
		
		return null;
	}
	
	public function postfix($lvl, $val){
		DEBUG('+ postfix_call');
		while ($this->toktype == self::TOK_OP){
			$oplvl = $this->findOperator(self::$POST_OPS, $lvl, $this->token);
			if ($oplvl == null){
				break;
			}
			
			if (is_callable($oplvl[1])){
				$val = $oplvl[1]($this, $val, $oplvl[0]);
			} else {
				$val = [$oplvl[1].'p', $val];
				$this->nextToken();
			}
			
			if ($this->toktype == self::TOK_NONE){
				break;
			}
		}
		
		DEBUG('- postfix_end');
		
		return $val;
	}
	
	public function prefix($lvl){
		DEBUG('+ prefix_call');
		switch ($this->toktype){
			case self::TOK_OP:
				$op = $this->token;
				if ($op == '#'){ //block
					if (!$this->nextToken() || $this->toktype != self::TOK_ID){
						$this->error('Block name excepted');
						return null;
					}
					
					$bname = $this->token;
					$args = [];
					
					if ($this->nextToken()){
						if ($this->toktype == self::TOK_OP && $this->token == '('){
							do {
								$this->nextToken();
								$args[] = $this->infix(1);
							} while ($this->toktype == self::TOK_OP && $this->token == ',');
							
							if ($this->toktype != self::TOK_OP || $this->token != ')'){
								$this->error('Excepted ")"');
								return null;
							}
							
							$this->nextToken();
						}
					}
					
					$val = ['b', $bname, $args];
					DEBUG('- prefix_end_block');
					return $this->postfix($lvl, $val);
				} else if ($op == '('){
					if (!$this->nextToken()){
						$this->error('Argument excepted in "("');
						return null;
					}
					
					$val = $this->infix(1);
					
					if ($this->toktype != self::TOK_OP || $this->token != ')'){
						$this->error('Excepted ")"');
						return null;
					}
					
					$this->nextToken();
					
					DEBUG('- prefix_end_)');
					return $this->postfix($lvl, $val);
				} else {
					$oplvl = $this->findOperator(self::$PRE_OPS, $lvl, $op);
					if ($oplvl == null){
						$this->error('Unexcepted operator "'.$this->token.'"');
						break;
					}
				
					if (!$this->nextToken()){
						$this->error('Unexcepted end of file. Excepted identificator or expression');
						break;
					}
				
					$val = $this->infix($oplvl[0]);
				
					if (is_callable($oplvl[1])){
						$val = $oplvl[1]($this, $val, $oplvl[0]);
					} else {
						$val = [$oplvl[1].'e', $val];
					}
				
					DEBUG('- prefix_end_op');
					return $this->postfix($lvl, $val);
				}
				
			case self::TOK_ID:
				switch ($this->token){
					case 'true': $res = ['r', true]; break;
					case 'false': $res = ['r', false]; break;
					case 'null': $res = ['r', null]; break;
					default: $res = ['l', $this->token]; break;
				}
				$this->nextToken();
				DEBUG('- prefix_end_id');
				return $this->postfix($lvl, $res);
				
			case self::TOK_NUM:
				$res = ['r', +$this->token];
				$this->nextToken();
				DEBUG('- prefix_end_num');
				return $this->postfix($lvl, $res);
				
			case self::TOK_STR:
				$res = ['r', $this->token];
				$this->nextToken();
				DEBUG('- prefix_end_str');
				return $this->postfix($lvl, $res);
				
			case self::TOK_NONE:
				$this->error('Unexcepted end of file');
				break;
				
			default:
				$this->error('Unknown token (type: '.$this->toktype.'): "'.$this->token.'"');
				break;
		}
		
		DEBUG('- prefix_end_null');
		return null;
	}
	
	public function infix($lvl){
		DEBUG('+ infix_call');
		
		$a1 = $this->prefix($lvl);
		
		while ($this->toktype == self::TOK_OP){
			$op = $this->token;
			$oplvl = $this->findOperator(self::$INF_OPS, $lvl, $op);
			if ($oplvl == null){
				DEBUG('- infix_end_1');
				return $a1;
			}
			
			if (!$this->nextToken()){
				$this->error('Unexcepted end of file. Operator excepted.');
				DEBUG('- infix_end_2');
				return null;
			}
			
			if (is_callable($oplvl[1])){
				$a1 = $oplvl[1]($this, $a1, $oplvl[0]);
			} else {
				$a2 = $this->infix($oplvl[0] + 1);
				$a1 = [$oplvl[1].'i', $a1, $a2];
			}
			
			$a1 = $this->postfix($lvl, $a1);
		}
		
		DEBUG('- infix_end_3');
		return $a1;
	}
}

TemplateLexer::_init();

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
			try {
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
			} catch (Exception $e){
				return 'Error: exception catched: '.$e->getMessage();
			}
		}
		return 'Error: template "'.$tplname.'" not found';
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
	
	private $lexer;
	
	private function __construct(){
		$this->tpl = '';
		$this->values = null;
		$this->cpos = 0;
		$this->res = '';
		$this->tplstack = null;
		$this->lastop = false;
		$this->pgm = [];
		$this->error = false;
		$this->blocks = [];
		$this->lexer = new TemplateLexer();
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
		if ($stripl){
			$str = preg_replace("/^( |\t)*\n/", "\n", $str, 1);
		}

		if ($stripr){
			$str = preg_replace("/\n( |\t)*$/", '', $str, 1);
		}

		$this->pgm[] = ['str', $str];
	}
	
	private function appendAndMoveTo($pos, $stripl = false, $stripr = false){
		if ($pos > $this->cpos){
			$this->append(substr($this->tpl, $this->cpos, $pos - $this->cpos), $stripl, $stripr);
		}
		$this->cpos = $pos;
	}

	public function parseValue($name){
		$this->lexer->setInput($name);
		$this->lexer->nextToken();
		return $this->lexer->parseExpression();
	}
	
	private function stripSpaces($str){
		return preg_replace('/^\s+|\s+$/', '', $str);
	}
	
	private function processStatement($stmt){
		$this->lexer->setInput($stmt);
		if ($this->lexer->nextToken()){
			if ($this->lexer->toktype == TemplateLexer::TOK_ID){
				switch ($this->lexer->token){
					case 'if':
						$pgm = ['if'];
						if ($this->lexer->nextToken()){
							$pgm[] = $this->lexer->parseExpression();
						
							$oldpgm = $this->pgm;
							$this->pgm = [];
							if ($this->parse() == 'else'){
								$pgm[] = $this->pgm;
								$this->pgm = [];
								if ($this->parse() != 'end'){
									$this->lexer->error('Excepted "end" for "if"');
								}
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
						$pgm = ['for'];
						if ($this->lexer->nextToken() && $this->lexer->toktype == TemplateLexer::TOK_ID){
							$pgm[] = $this->lexer->token;
							
							if (!$this->lexer->nextToken() || $this->lexer->toktype != TemplateLexer::TOK_ID){
								$this->lexer->error('Excepted "in" in "for"');
							}
							if (!$this->lexer->nextToken()){
								$this->lexer->error('Excepted array in "for"');
							}
							$pgm[] = $this->lexer->parseExpression();
							
							$oldpgm = $this->pgm;
							$this->pgm = [];
							$this->parse();
						
							$pgm[] = $this->pgm;
							$this->pgm = $oldpgm;
							$this->pgm[] = $pgm;
						} else {
							$this->lexer->error('Identifier excepted after "for"');
						}
						break;
					
					case 'include':
						$pgm = ['incl'];
						
						if (!$this->lexer->nextToken() || !($this->lexer->toktype == TemplateLexer::TOK_ID || $this->lexer->toktype == TemplateLexer::TOK_STR)){
							$this->lexer->error('Excepted including template name');
						}
						
						$pgm[] = $this->lexer->token;
						
						$args = [];
						while ($this->lexer->nextToken()){
							$args[] = $this->lexer->parseExpression();
						}
						
						$acnt = count($args);
						if ($acnt > 0){
							if ($acnt == 1){
								if ($args[0][0] == 'r' || $args[0][0] == 'b'){
									$pgm[] = true;
									$pgm[] = $args;
								} else {
									$pgm[] = false;
									$pgm[] = $args[0];
								}
							} else {
								$pgm[] = true;
								$pgm[] = $args;
							}
						} else {
							$pgm[] = false;
							$pgm[] = null;
						}
						
						$this->pgm[] = $pgm;
						break;
					
					case 'block':
						if ($this->lexer->nextToken() && $this->lexer->toktype == TemplateLexer::TOK_ID){
							$bname = $this->lexer->token;
							
							$oldpgm = $this->pgm;
							$this->pgm = [];
							$this->parse();
						
							$this->blocks[$bname] = $this->pgm;
							$this->pgm = $oldpgm;
						} else {
							$this->lexer->error('Excepted block name');
						}
						break;
				}
			} else {
				$this->lexer->error('Unexcepted token: '.$this->lexer->token);
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
					$stmt = substr($this->tpl, $tpos, $this->cpos - $tpos - 2);
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
	
	private function applyFunction($v, $func, $fargs){
		$facnt = count($fargs);
		for ($i = 0; $i < $facnt; ++$i){
			$fargs[$i] = $this->readValue($fargs[$i]);
		}
		
		switch ($func){
			case 'safe': $v = htmlspecialchars($v); break;
			case 'text': $v = str_replace(["\n", '  ', "\t"], ["\n<br>", ' &nbsp;', ' &nbsp; &nbsp;'], htmlspecialchars($v)); break;
		
			case 'lowercase': $v = mb_strtolower($v, 'utf-8'); break;
			case 'uppercase': $v = mb_strtoupper($v, 'utf-8'); break;
		
			case 'url': $v = htmlspecialchars($v); break;
			case 'urlparam': $v = urlencode($v); break;
		
			case 'json': $v = json_encode($v); break;
		
			case 'count': $v = count($v); break;
			
			case 'isarray': $v = is_array($v); break;
			case 'keys': $v = array_keys($v); break;
		
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
			
			case 'bbcode':
				$v = BBCode::process($v);
				break;
			
			default:
				foreach (self::$USER_FUNCS as $f){
					$v = $f($v, $func, $fargs);
				}
				break;
		}
		
		return $v;
	}
	
	/**
	 * Program arrays:
	 * ['str', $string]
	 * ['var', $var]
	 * ['if', $var, $body_true, $body_false]
	 * ['incl', $tpl, $isarr, [$arg1, $arg2, ...]]
	 * ['for', $i, $var, $body]
	 * 
	 * Value arrays:
	 * ['r', $value]
	 * ['l', $name]
	 * ['b', $block, $arg]
	 *
	 * [$op, $args...]
	 * ['|p', $val, $fname, [$arg1, $arg2, ...]]
	 * ['[p', $val, $key]
	 */
	
	private function readValue($op){
		switch ($op[0]){
			case 'r':
				return $op[1];
	
			case 'l':
				if ($op[1] == 'this') return $this->values;
				return array_key_exists($op[1], $this->values) ? $this->values[$op[1]] : false;
				
			case '[p':
				$v = $this->readValue($op[1]);
				if (!is_array($v))
					return false;
					
				$k = ''.$this->readValue($op[2]);
				if (!array_key_exists($k, $v))
					return false;
					
				return $v[$k];
				
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
				
				$v2 = $op[2][0] == 'l' ? $op[2][1] : ''.$this->readValue($op[2]);
				
				if ($v1 === false || !is_array($v1) || !array_key_exists($v2, $v1)){
					return false;
				}
				return $v1[$v2];
				
			case '+e': return +$this->readValue($op[1]);
			case '-e': return -$this->readValue($op[1]);
			case '!e': return !$this->readValue($op[1]);
			
			case '+i':
				$v1 = $this->readValue($op[1]);
				$v2 = $this->readValue($op[2]);
				if (is_string($v1)) return $v1.$v2;
				else return $v1 + $v2;
				
			case '-i': return $this->readValue($op[1]) - $this->readValue($op[2]);
			case '*i': return $this->readValue($op[1]) * $this->readValue($op[2]);
			case '/i': return $this->readValue($op[1]) / $this->readValue($op[2]);
			
			case '==i': return $this->readValue($op[1]) == $this->readValue($op[2]);
			case '===i': return $this->readValue($op[1]) === $this->readValue($op[2]);
			case '!=i': return $this->readValue($op[1]) != $this->readValue($op[2]);
			case '!==i': return $this->readValue($op[1]) !== $this->readValue($op[2]);
			case '>i': return $this->readValue($op[1]) > $this->readValue($op[2]);
			case '<i': return $this->readValue($op[1]) < $this->readValue($op[2]);
			case '>=i': return $this->readValue($op[1]) >= $this->readValue($op[2]);
			case '<=i': return $this->readValue($op[1]) <= $this->readValue($op[2]);
			
			case 'andi': return $this->readValue($op[1]) && $this->readValue($op[2]);
			case 'ori': return $this->readValue($op[1]) || $this->readValue($op[2]);
			case 'xori': return $this->readValue($op[1]) ^ $this->readValue($op[2]);
				
			default:
				return false;
		}
		
		return false;
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

