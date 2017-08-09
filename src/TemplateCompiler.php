<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class TemplateCompiler {
	private $options;
	private $lastop;
	private $pgm;
	private $lexer;
	private $endesc;
	private $res;

	public function __construct(TemplateOptions $options){
		$this->options = $options;
		$this->endesc = '';
		$this->lastop = false;
		$this->pgm = [];
		$this->lexer = new TemplateLexer();
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

	private function processStatement(){
		if ($this->lexer->toktype != TemplateLexer::TOK_NONE){
			if ($this->lexer->toktype == TemplateLexer::TOK_ID){
				switch ($this->lexer->token){
					case 'if':
						$this->processStatementIf();
						break;

					case 'for':
						$pgm = ['fore'];
						if ($this->lexer->nextToken() && $this->lexer->toktype == TemplateLexer::TOK_ID){
							$pgm[] = $this->lexer->token;

							if (!$this->lexer->nextToken()){
								$this->lexer->error('Excepted "in" or "=" in "for"');
							}

							if ($this->lexer->isToken(TemplateLexer::TOK_ID, 'in')){
								if (!$this->lexer->nextToken()){
									$this->lexer->error('Excepted array in "for"');
								}
								$pgm[] = $this->lexer->parseExpression();
							} else if ($this->lexer->isToken(TemplateLexer::TOK_OP, '=')){
								$pgm[0] = 'for';

								if (!$this->lexer->nextToken()){
									$this->lexer->error('Excepted initializer in "for"');
								}
								$pgm[] = $this->lexer->parseExpression();

								if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'while')){
									$this->lexer->error('Excepted "while" in "for"');
								}
								if (!$this->lexer->nextToken()){
									$this->lexer->error('Excepted condition in "for"');
								}
								$pgm[] = $this->lexer->parseExpression();

								if (!$this->lexer->isToken(TemplateLexer::TOK_ID, 'next')){
									$this->lexer->error('Excepted "next" in "for"');
								}
								if (!$this->lexer->nextToken()){
									$this->lexer->error('Excepted post-iteration action in "for"');
								}
								$pgm[] = $this->lexer->parseExpression();
							} else {
								$this->lexer->error('Excepted "in" or "=" in "for"');
							}

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

					case 'include_once':
					case 'include':
						$pgm = [$this->lexer->token == 'include' ? 'incl' : 'inclo'];

						if (
							!$this->lexer->nextToken()
							|| !($this->lexer->toktype == TemplateLexer::TOK_ID
								|| $this->lexer->toktype == TemplateLexer::TOK_STR)
						){
							$this->lexer->error('Excepted including template name');
						}

						$pgm[] = $this->lexer->token;

						$args = [];
						$this->lexer->nextToken();
						while (
							$this->lexer->toktype != TemplateLexer::TOK_NONE
							&& $this->lexer->toktype != TemplateLexer::TOK_ESC
						){
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
						$this->processExpression();
						break;
				}
			} else {
				$this->processExpression();
			}
		}
	}

	private function processStatementIf(){
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
								$this->lexer->error('Excepted ' . $this->endesc . ', but }} found');
							}
							$this->endesc = '';

							$this->lexer->nextToken();
							break;

						case '?}':
							if ($this->endesc != $this->lexer->token){
								$this->lexer->error('Excepted ' . $this->endesc . ', but ?} found');
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
							if (
								!($this->lexer->isToken(TemplateLexer::TOK_ESC, '>>')
								|| $this->lexer->isToken(TemplateLexer::TOK_ESC, '/>>'))
							){
								$this->lexer->error('Excepted >>');
							}
							$this->lexer->nextToken();
							$this->lastop = false;
							break;

						default:
							$this->lexer->error('Unexcepted escape: ' . $this->lexer->token);
							break;
					}
					break;

				default:
					if ($this->endesc == '}}'){
						$this->processExpression();
						$arg = end($this->pgm)[1];
						if (
							$this->options->getAutoSafeEnabled()
							&& ($arg[0] !== '|p' || !in_array($arg[2], Template::AUTOSAFE_IGNORE))
						){
							// заэкранируем вывод, для этого вложим весь вывод в пайп-функцию экранирования
							$arg = ['|p', $arg, 'safe', []];
							$this->pgm[key($this->pgm)][1] = $arg;
						}
					} else {
						if (
							$this->lexer->toktype == TemplateLexer::TOK_ID
							&& in_array($this->lexer->token, ['end', 'else'])
						){
							return true;
						}

						$this->processStatement();
					}
					break;
			}
		}

		return false;
	}

	private function append($str, $stripl = false, $stripr = false){
		if ($stripl){
			$str = preg_replace("/^( |\t)*(\r\n|[\r\n])/", '$2', $str, 1);
		}

		if ($stripr){
			$str = preg_replace("/(\r\n|[\r\n])( |\t)*$/", '', $str, 1);
		}

		if ($str != '')
			$this->pgm[] = ['str', $str];
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
						$this->lexer->error('Invalid end widget name, excepted "' . $wname . '"');
					}

					$this->lexer->nextToken();
				}

				$this->pgm[] = ['widg', $wname, $attrs, $body];
			}
		}
	}

	private function processExpression(){
		$arg = $this->lexer->parseExpression();
		if (in_array($arg[0], ['=i', '+=i', '-=i', '*=i', '/=i'])){
			$this->pgm[] = ['calc', $arg];
		} else {
			$this->pgm[] = ['var', $arg];
		}
	}
}
