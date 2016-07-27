<?php

/**
* Author: Assasin (assasin@sinair.ru)
* License: beerware
* Use for good
*/

require_once 'bbcode.php';

$PDEBUG = false;

function DEBUG($str){
	global $PDEBUG;
	if ($PDEBUG)
		echo htmlspecialchars($str)."\n<br>";
}

class TemplateLexer {
	private $input;
	private $ilen;
	public $toktype;
	public $token;
	private $cpos;
	private $cline;
	private $state;
	
	const TOK_NONE = 0;
	const TOK_ID = 1;
	const TOK_OP = 2;
	const TOK_STR = 3;
	const TOK_NUM = 4;
	const TOK_INLINE = 5;
	const TOK_ESC = 6;
	const TOK_UNK = 10;
	
	const STATE_TEXT = 0;
	const STATE_CODE = 1;
	
	const OPERATORS = '+-*/|&.,@#$!?:;~%^=<>()[]{}';
	const TERMINAL_OPERATORS = '.,@#;()[]';
	const ID_OPERATORS = ['and', 'or', 'xor'];
	
	private static $PRE_OPS;
	private static $INF_OPS;
	private static $POST_OPS;
	
	public static function _init(){
		self::$PRE_OPS = [
			8 => ['+', '-', '!'],
			9 => ['$'],
		];
		
		self::$INF_OPS = [
			1 => ['or'],
			2 => ['xor'],
			3 => ['and'],
			4 => ['==', '===', '!=', '!==', '>=', '<=', '<', '>'],
			5 => ['+', '-'],
			6 => ['*', '/'],
			7 => ['^'],
			
			9 => ['.'],
		];
		
		self::$POST_OPS = [
			8 => [
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
				
				'(' => function($parser, $val, $lvl){
					DEBUG('+ operator_(p_call');
					$args = [];
					
					$parser->nextToken();
					if (!$parser->isToken(self::TOK_OP, ')')){
						$args[] = $parser->infix(1);
						while ($parser->isToken(self::TOK_OP, ',')){
							$args[] = $parser->infix(1);
							$parser->nextToken();
						}
					
						if (!$parser->isToken(self::TOK_OP, ')')){
							$parser->error('Excepted ")" in function call');
							return null;
						}
					}
					
					$parser->nextToken();
					
					DEBUG('- operator_(p_end');
					return ['(p', $val, $args];
				},
			],
		];
	}
	
	public function __construct(){
		$this->toktype = self::TOK_NONE;
		$this->token = '';
	}
	
	public function setInput($str, $st = 0){ //STATE_TEXT
		$this->input = $str;
		$this->ilen = strlen($str);
		$this->cpos = 0;
		$this->cline = 1;
		$this->state = $st;
	}
	
	public function error($msg){
		throw new Exception('line '.$this->cline.': '.$msg);
	}
	
	public function isToken($type, $val){
		return $this->toktype == $type && $this->token == $val;
	}
	
	public function nextToken(){
		switch ($this->state){
			case self::STATE_TEXT:
				$res = $this->nextToken_text();
				break;
			
			case self::STATE_CODE:
				$res = $this->nextToken_code();
				break;
		}
		DEBUG('Token: '.($res ? '['.$this->toktype.', "'.$this->token.'"]' : 'end'));
		return $res;
	}
	
	public function nextToken_comment(){
		$cpos = $this->cpos;
		
		while ($cpos < $this->ilen){
			$c = $this->input{$cpos};
			
			if ($c != '*'){
				if ($c == "\n"){
					++$this->cline;
				}
				++$cpos;
				continue;
			}
			
			++$cpos;
			
			if ($cpos >= $this->ilen){
				$cpos = $this->ilen;
				break;
			}
			
			$c = $this->input{$cpos};
			if ($c == '}'){
				++$cpos;
				break;
			}
		}
		
		$this->cpos = $cpos;
	}
	
	public function nextToken_text(){
		$this->token = '';
		
		$cpos = $this->cpos;
		if ($cpos >= $this->ilen){
			$this->toktype = self::TOK_NONE;
			return false;
		}
		
		while ($cpos < $this->ilen){
			$c = $this->input{$cpos};
			
			if ($c != '{' && $c != '<'){
				if ($c == "\n"){
					++$this->cline;
				}
				++$cpos;
				continue;
			}
			
			if ($cpos + 1 >= $this->ilen){
				$cpos = $this->ilen;
				break;
			}
			
			$c2 = $this->input{$cpos + 1};
			
			if ($c2 == '*'){
				$this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
				$this->cpos = $cpos + 1;
				$this->nextToken_comment();
				$cpos = $this->cpos;
				continue;
			} else if ($c == '{' && ($c2 == '{' || $c2 == '?') || $c == '<' && $c2 == '<'){
				$this->state = self::STATE_CODE;
				break;
			}
			
			++$cpos;
		}
		
		if ($this->cpos == $cpos){
			return $this->nextToken_code();
		}
		$this->toktype = self::TOK_INLINE;
		$this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
		$this->cpos = $cpos;
		
		return true;
	}
	
	/* State Machine:
	 * Реализация проверки операторов += -=
	 * [[
	 *   '+' => 1,
	 *   '-' => 1,
	 * ],[
	 *   '=' => true,
	 * ]]
	 * return: false если машина получила false, иначе строку
	 */
	private function checkNextSM($pos, $m){
		$res = '';
		$state = 0;
		while ($pos < $this->ilen){
			$ch = $this->input{$pos};
			if (!array_key_exists($ch, $m[$state])){
				return false;
			}
			
			$res .= $ch;
			$state = $m[$state][$ch];
			
			if ($state === false){
				return false;
			} else if ($state === true){
				return $res;
			}
			
			++$pos;
		}
	}
	
	public function nextToken_code(){
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
			$this->token = '';
			
			while ($cpos < $this->ilen){
				$ch = $this->input{$cpos};
				if ($ch != $esym){
					if ($ch == "\n"){
						++$this->cline;
					}
					
					if ($ch == '\\' && $cpos + 1 < $this->ilen){
						$ch2 = $this->input{$cpos + 1};
						$rch = '';
						switch($ch2){
							case 'n': $rch = "\n"; break;
							case 'r': $rch = "\r"; break;
							case 't': $rch = "\t"; break;
							case '\'': $rch = '\''; break;
							case '"': $rch = '"'; break;
							case '\\': $rch = '\\'; break;
							default:
								break;
						}
						
						if ($rch){
							$this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
							$this->token .= $rch;
							++$cpos;
							$this->cpos = $cpos + 1;
						}
					}
					
					++$cpos;
				} else {
					break;
				}
			}
			
			if ($cpos >= $this->ilen || $this->input{$cpos} != $esym){
				$this->error('Excepted '.$esym);
			}
			
			$this->toktype = self::TOK_STR;
			$this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
			$this->cpos = $cpos + 1;
			
			return true;
		}
		else if (strpos(self::OPERATORS, $ch) !== false){
			$this->toktype = self::TOK_OP;
			++$cpos;
			
			// {{ {? ?} }} << >> />>
			$smres = $this->checkNextSM($cpos - 1, [
				0 => [
					'{' => 1,
					'?' => 2,
					'}' => 2,
					'<' => 3,
					'>' => 4,
					'/' => 5,
					';' => true,
				],
				1 => [
					'{' => true,
					'?' => true,
				],
				2 => [
					'}' => true,
				],
				3 => [
					'<' => true,
				],
				4 => [
					'>' => true,
				],
				5 => [
					'>' => 4,
				],
			]);
			
			if ($smres !== false){
				$this->token = $smres;
				$this->toktype = self::TOK_ESC;
				
				if ($ch != '{' && $ch != '<' && $ch != ';'){
					$this->state = self::STATE_TEXT;
				}
				$this->cpos = $cpos + strlen($smres) - 1;
				
				return true;
			}

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
	
	public function getTokenStr(){
		return '['.$this->toktype.', "'.$this->token.'"]';
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
				} else if ($op == '$'){
					$gvname = null;
					
					$this->nextToken();
					if ($this->toktype == self::TOK_ID){
						$gvname = $this->token;
						$this->nextToken();
					}
					
					$val = ['g', $gvname];
					DEBUG('- prefix_end_$');
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

class TemplateCompiler {
	private $lastop;
	private $pgm;
	private $lexer;
	private $endesc;
	
	public function __construct(){
		$this->endesc = '';
		$this->lastop = false;
		$this->pgm = [];
		$this->lexer = new TemplateLexer();
	}
	
	public function getResult(){
		return $this->res;
	}
	
	private function append($str, $stripl = false, $stripr = false){
		if ($stripl){
			$str = preg_replace("/^( |\t)*\n/", "\n", $str, 1);
		}

		if ($stripr){
			$str = preg_replace("/\n( |\t)*$/", '', $str, 1);
		}
		
		if ($str != '')
			$this->pgm[] = ['str', $str];
	}
	
	private function processStatementIf(){
		DEBUG('+ if call');
		$pgm = ['if'];
		if ($this->lexer->nextToken()){
			$pgm[] = $this->lexer->parseExpression();
		
			$oldpgm = $this->pgm;
			$this->pgm = [];

			$this->parse();
			
			if ($this->lexer->isToken(TemplateLexer::TOK_ID, 'else')){
				$pgm[] = $this->pgm;
				$this->pgm = [];
				
				if (!$this->lexer->nextToken()){
					$this->lexer->error('Excepted "end" for "if"');
				}
				
				$this->lastop = true;
				if ($this->lexer->isToken(TemplateLexer::TOK_ID, 'if')){
					$this->processStatementIf();
				
					$pgm[] = $this->pgm;
					
					$this->pgm = $oldpgm;
					$this->pgm[] = $pgm;
					
					return;
				} else {
					$this->parse();
					if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'end')){
						$this->lexer->error('Excepted "end" for "if"');
					}
					$pgm[] = $this->pgm;
				}
			} else if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'end')){
				$this->lexer->error('Excepted "end" for "if"');
			} else {
				$pgm[] = $this->pgm;
				$pgm[] = [];
			}
			$this->pgm = $oldpgm;
			$this->pgm[] = $pgm;
			
			$this->lexer->nextToken();
		} else {
			$this->lexer->error('Excepted condition in "if"');
		}
		DEBUG('- if call');
	}
	
	private function processStatement(){
		if ($this->lexer->toktype != TemplateLexer::TOK_NONE){
			if ($this->lexer->toktype == TemplateLexer::TOK_ID){
				switch ($this->lexer->token){
					case 'if':
						$this->processStatementIf();
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
							
							if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'end')){
								$this->lexer->error('Excepted "end" in "for"');
							}
							$this->lexer->nextToken();
						
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
						$this->lexer->nextToken();
						while ($this->lexer->toktype != TemplateLexer::TOK_NONE && $this->lexer->toktype != TemplateLexer::TOK_ESC){
							$args[] = $this->lexer->parseExpression();
						}
						
						$acnt = count($args);
						if ($acnt > 0){
							if ($acnt > 1 || $args[0][0] == 'r' || $args[0][0] == 'b'){
								$pgm[] = true;
								$pgm[] = $args;
							} else {
								$pgm[] = false;
								$pgm[] = $args[0];
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
							
							if (!$this->lexer->nextToken()){
								$this->lexer->error('Excepted "end" for "block"');
							}
							
							$oldpgm = $this->pgm;
							$this->pgm = [];
							$this->parse();
							
							if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'end')){
								$this->lexer->error('Excepted "end" in "block"');
							}
							$this->lexer->nextToken();
							
							$pgm = ['regb', $bname, $this->pgm];
							$this->pgm = $oldpgm;
							$this->pgm[] = $pgm;
						} else {
							$this->lexer->error('Excepted block name');
						}
						break;
						
					case 'widget':
						if ($this->lexer->nextToken() && $this->lexer->toktype == TemplateLexer::TOK_ID){
							$wname = $this->lexer->token;							
							
							if (!$this->lexer->nextToken()){
								$this->lexer->error('Excepted "end" for "widget"');
							}
							
							$oldpgm = $this->pgm;
							$this->pgm = [];
							$this->parse();
							
							if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'end')){
								$this->lexer->error('Excepted "end" in "widget"');
							}
							$this->lexer->nextToken();
							
							$pgm = ['regw', $wname, $this->pgm];
							$this->pgm = $oldpgm;
							$this->pgm[] = $pgm;
						} else {
							$this->lexer->error('Excepted widget name');
						}
						break;
						
					default:
						$this->pgm[] = ['var', $this->lexer->parseExpression()];
						break;
				}
			} else {
				$this->pgm[] = ['var', $this->lexer->parseExpression()];
				//$this->lexer->error('Unexcepted token('.$this->lexer->toktype.'): '.$this->lexer->token);
			}
		}
	}
	
	private function processWidget(){
		if ($this->lexer->toktype != TemplateLexer::TOK_NONE){
			if ($this->lexer->toktype == TemplateLexer::TOK_ID){
				$wname = $this->lexer->token;
				$attrs = [];
				
				$this->lexer->nextToken();
				while ($this->lexer->toktype == TemplateLexer::TOK_ID){
					$aname = $this->lexer->token;
					
					$this->lexer->nextToken();
					if (!$this->lexer->isToken(TemplateLexer::TOK_OP, '=')){
						$attrs[$aname] = ['r', true];
					} else {
						$this->lexer->nextToken();
						$attrs[$aname] = $this->lexer->parseExpression();
					}
				}
				
				$autoclose = $this->lexer->isToken(TemplateLexer::TOK_ESC, '/>>');
				
				if (!$autoclose && !$this->lexer->isToken(TemplateLexer::TOK_ESC, '>>')){
					$this->lexer->error('Excepted >>');
				}
				
				if ($autoclose){
					$body = [];
				} else {
					$oldpgm = $this->pgm;
					$this->pgm = [];
					$this->lexer->nextToken();
					$this->parse();
				
					$body = $this->pgm;
					$this->pgm = $oldpgm;
				
					if (!$this->lexer->isToken(TemplateLexer::TOK_OP, '/')){
						$this->lexer->error('Excepted end of widget');
					}
				
					$this->lexer->nextToken();
					if (!$this->lexer->isToken(TemplateLexer::TOK_ID, $wname)){
						$this->lexer->error('Invalid end widget name, excepted "'.$wname.'"');
					}
					
					$this->lexer->nextToken();
				}
				
				$this->pgm[] = ['widg', $wname, $attrs, $body];
			}
		}
	}
	
	public function parse(){
		while ($this->lexer->toktype != TemplateLexer::TOK_NONE){
			switch ($this->lexer->toktype){
				case TemplateLexer::TOK_INLINE:
					$str = $this->lexer->token;
					$this->lexer->nextToken();
					$this->append($str, $this->lastop, $this->lexer->isToken(TemplateLexer::TOK_ESC, '{?'));
					$this->lastop = false;
					continue;
					break;
					
				case TemplateLexer::TOK_ESC:
					switch ($this->lexer->token){
						case '{{':
							$this->endesc = '}}';
							$this->lexer->nextToken();
							break;
					
						case '{?':
							$this->endesc = '?}';
							$this->lexer->nextToken();
							break;
						
						case '}}':
							if ($this->endesc != $this->lexer->token){
								$this->lexer->error('Excepted '.$this->endesc.', but }} found');
							}
							$this->endesc = '';
							
							$this->lexer->nextToken();
							break;
						
						case '?}':
							if ($this->endesc != $this->lexer->token){
								$this->lexer->error('Excepted '.$this->endesc.', but ?} found');
							}
							$this->endesc = '';
							
							$this->lexer->nextToken();
							$this->lastop = true;
							break;
							
						case ';':
							$this->lexer->nextToken();
							break;
							
						case '<<':
							$this->lexer->nextToken();
							if ($this->lexer->isToken(TemplateLexer::TOK_OP, '/')){
								return true;
							}
							$this->processWidget();
							if (!($this->lexer->isToken(TemplateLexer::TOK_ESC, '>>') || $this->lexer->isToken(TemplateLexer::TOK_ESC, '/>>'))){
								$this->lexer->error('Excepted >>');
							}
							$this->lexer->nextToken();
							$this->lastop = false;
							break;
							
						default:
							$this->lexer->error('Unexcepted escape: '.$this->lexer->token);
							break;
					}
					break;
				
				default:
					if ($this->endesc == '}}'){
						$this->pgm[] = ['var', $this->lexer->parseExpression()];
					} else {
						if ($this->lexer->toktype == TemplateLexer::TOK_ID && in_array($this->lexer->token, ['end', 'else'])){
							return true;
						}
						$this->processStatement();
					}
					break;
			}
		}
		return false;
	}
	
	public function compile($tpl){
		$this->lastop = false;
		$this->pgm = [];
		
		$this->lexer->setInput($tpl);
		$this->lexer->nextToken();
		$this->parse();
		
		return true;
	}
	
	public function getProgram(){
		return $this->pgm;
	}
}

class DelayedProgram {
	private $tpl;
	private $pgm;
	private $values;
	
	public function __construct(Template $tpl, $pgm, $values){
		$this->tpl = $tpl;
		$this->pgm = $pgm;
		$this->values = $values;
	}
	
	public function __toString(){
		$oldvals = $this->tpl->values;
		$oldres = $this->tpl->res;
		
		$this->tpl->values = $this->values;
		$this->tpl->res = '';
		
		$this->tpl->execPgm($this->pgm);
		$res = $this->tpl->res;
		
		$this->tpl->values = $oldvals;
		$this->tpl->res = $oldres;
		
		return $res;
	}
}

class Template {
	
	public static $TPL_PATH = './';
	
	private static $TPL_CACHE = [];
	private static $USER_FUNCS = [];
	private static $GLOB_VARS = [];
	
	public static function init($tplpath){
		self::$TPL_PATH = $tplpath;
	}
	
	public static function addUserFunctionHandler($f){
		if (is_callable($f)){
			self::$USER_FUNCS[] = $f;
		}
	}
	
	public static function addGlobalVar($name, $val){
		self::$GLOB_VARS[$name] = $val;
	}
	
	/**
	 * Вставляет в шаблон $tplname переменные из массива $values
	 * $tplname - имя шаблона
	 * $values - ассоциативный массив параметров вида ['arg' => 'val'].
	 */
	public static function build($tplname, array $values){
		$p = self::compile($tplname);
		if (is_string($p)){
			return $p;
		}
		$p->run($values);
		return $p->getResult();
	}
	
	public static function build_str($tplstr, array $values){
		$c = new TemplateCompiler();
		$c->compile($tplstr);
		$p = new Template($c->getProgram());
		$p->run($values);
		return $p->getResult();
	}
	
	private static function compile($tplname){
		$tpath = self::$TPL_PATH.$tplname.'.html';

		if (file_exists($tpath)){
			try {
				$p = null;
				if (array_key_exists($tpath, self::$TPL_CACHE)){
					$p = self::$TPL_CACHE[$tpath];
				} else {
					$c = new TemplateCompiler();
					$c->compile(file_get_contents($tpath));
					$p = new Template($c->getProgram());
					self::$TPL_CACHE[$tpath] = $p;
				}
			
				return $p;
			} catch (Exception $e){
				return 'Error: '.$tplname.'.html, '.$e->getMessage();
			}
		}
		return 'Error: template "'.$tplname.'" not found';
	}

	public $pgm;
	public $values;
//	private $tplstack;
	private $blocks;
	private $widgets;
	public $res;
	
	private function __construct($pgm){
		$this->pgm = $pgm;
		$this->values = [];
		$this->res = '';
//		$this->tplstack = null;
		$this->blocks = [];
		$this->widgets = [];
	}
	
	public function getResult(){
		return $this->res;
	}
	
	public function run($values){
		$this->values = $values;
		$this->res = '';
//		$this->tplstack = $tplstack;
		
		$this->execPgm($this->pgm);
		
		$this->values = [];
//		$this->tplstack = [];
		$this->blocks = [];
		$this->widgets = [];
		
		return true;
	}
	
	private function applyFunction($v, $func, $fargs){
		$facnt = count($fargs);
		for ($i = 0; $i < $facnt; ++$i){
			$fargs[$i] = $this->readValue($fargs[$i]);
		}
		
		switch ($func){
			case 'safe': $v = htmlspecialchars($v); break;
			case 'text': $v = str_replace(["\n", '  ', "\t"], ["\n<br>", '&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'], htmlspecialchars($v)); break;
		
			case 'lowercase': $v = mb_strtolower($v, 'utf-8'); break;
			case 'uppercase': $v = mb_strtoupper($v, 'utf-8'); break;
		
			case 'url': $v = htmlspecialchars($v); break;
			case 'urlparam': $v = rawurlencode($v); break;
		
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
				$k = ''.$this->readValue($op[2]);
				
				if ($v === false){
					return false;
				}
				else if (is_array($v)){	
					return array_key_exists($k, $v) ? $v[$k] : false;
				}
				else if (method_exists($v, $k)){
					return function() use($v, $k) { return call_user_func_array([$v, $k], func_get_args()); };
				}
				else if (isset($v->$k)){
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
				
				$v2 = $op[2][0] == 'l' ? $op[2][1] : ''.$this->readValue($op[2]);
				
				if ($v1 === false){
					return false;
				}
				else if (is_array($v1)){
					return array_key_exists($v2, $v1) ? $v1[$v2] : false;
				}
				else if (method_exists($v1, $v2)){
					return function() use($v1, $v2) { return call_user_func_array([$v1, $v2], func_get_args()); };
				}
				else if (isset($v1->$v2)){
					return $v1->$v2;
				}
				
				return false;
				
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
	
	public function execPgm($pgm){
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
					
					$p = self::compile($ins[1]);
					if (is_string($p)){
						$this->res .= $p;
					} else {
						$oldvals = $this->values;
						$this->values = $arg;
						
						$this->execPgm($p->pgm);
						
						$this->values = $oldvals;
					}
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
						$this->res .= 'Error: widget '.$ins[1].' not found';
					}
					break;
			}
		}
	}
}

