<?php
/**
 * Author: Assasin (assasin@sinair.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class TemplateLexer
{
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
    const TERMINAL_OPERATORS = '.,@#;()[]$';
    const ID_OPERATORS = ['and', 'or', 'xor', 'not'];
    private static $PRE_OPS;
    private static $INF_OPS;
    private static $POST_OPS;
    public $toktype;
    public $token;
    private $input;
    private $ilen;
    private $cpos;
    private $cline;
    private $state;

    public function __construct()
    {
        $this->toktype = self::TOK_NONE;
        $this->token = '';
    }

    public static function _init()
    {
        self::$PRE_OPS = [
            10 => ['+', '-', '!', 'not'],
            11 => ['$'],
        ];

        self::$INF_OPS = [
            1 => ['=', '+=', '-=', '*=', '/='],
            2 => ['??'],
            3 => ['or'],
            4 => ['xor'],
            5 => ['and'],
            6 => ['==', '===', '!=', '!==', '>=', '<=', '<', '>'],
            7 => ['+', '-'],
            8 => ['*', '/'],
            9 => ['^'],

            11 => ['.'],
        ];

        self::$POST_OPS = [
            10 => [
                '|' => function (TemplateLexer $parser, $val, $lvl) {
                    DEBUG('+ operator_|p_call');
                    if (!$parser->nextToken() || $parser->toktype != self::TOK_ID) {
                        $parser->error('Function name excepted in "|"');
                        return null;
                    }

                    $fname = $parser->token;
                    $args = [];

                    if ($parser->nextToken()) {
                        if ($parser->toktype == self::TOK_OP && $parser->token == '(') {
                            do {
                                $parser->nextToken();
                                $args[] = $parser->infix(1);
                            } while ($parser->toktype == self::TOK_OP && $parser->token == ',');

                            if ($parser->toktype != self::TOK_OP || $parser->token != ')') {
                                $parser->error('Excepted ")" in "|"');
                                return null;
                            }

                            $parser->nextToken();
                        }
                    }

                    DEBUG('- operator_|p_end');
                    return ['|p', $val, $fname, $args];
                },

                '[' => function (TemplateLexer $parser, $val, $lvl) {
                    DEBUG('+ operator_[p_call');

                    if (!$parser->nextToken()) {
                        $parser->error('Argument excepted in "["');
                        return null;
                    }

                    $arg = $parser->infix(1);

                    if ($parser->toktype != self::TOK_OP || $parser->token != ']') {
                        $parser->error('Excepted "]"');
                        return null;
                    }

                    $parser->nextToken();

                    DEBUG('- operator_[p_end');
                    return ['[p', $val, $arg];
                },

                '(' => function (TemplateLexer $parser, $val, $lvl) {
                    DEBUG('+ operator_(p_call');
                    $args = [];

                    $parser->nextToken();
                    if (!$parser->isToken(self::TOK_OP, ')')) {
                        $args[] = $parser->infix(1);
                        while ($parser->isToken(self::TOK_OP, ',')) {
                            $args[] = $parser->infix(1);
                            $parser->nextToken();
                        }

                        if (!$parser->isToken(self::TOK_OP, ')')) {
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

    public function setInput($str, $st = 0)
    { //STATE_TEXT
        $this->input = $str;
        $this->ilen = strlen($str);
        $this->cpos = 0;
        $this->cline = 1;
        $this->state = $st;
    }

    public function isToken($type, $val)
    {
        return $this->toktype == $type && $this->token == $val;
    }

    public function getToken()
    {
        return [$this->toktype, $this->token];
    }

    public function getTokenStr()
    {
        return '[' . $this->toktype . ', "' . $this->token . '"]';
    }

    public function parseExpression()
    {
        return $this->infix(1);
    }

    public function infix($lvl)
    {
        DEBUG('+ infix_call');

        $a1 = $this->prefix($lvl);

        while ($this->toktype == self::TOK_OP) {
            $op = $this->token;
            $oplvl = $this->findOperator(self::$INF_OPS, $lvl, $op);
            if ($oplvl == null) {
                DEBUG('- infix_end_1');
                return $a1;
            }

            if (!$this->nextToken()) {
                $this->error('Unexcepted end of file. Operator excepted.');
                DEBUG('- infix_end_2');
                return null;
            }

            if (is_callable($oplvl[1])) {
                $a1 = $oplvl[1]($this, $a1, $oplvl[0]);
            } else {
                $a2 = $this->infix($oplvl[0] + 1);
                $a1 = [$oplvl[1] . 'i', $a1, $a2];
            }

            $a1 = $this->postfix($lvl, $a1);
        }

        DEBUG('- infix_end_3');
        return $a1;
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

    public function prefix($lvl)
    {
        DEBUG('+ prefix_call');
        switch ($this->toktype) {
            case self::TOK_OP:
                $op = $this->token;
                if ($op == '#') { //block
                    if (!$this->nextToken() || $this->toktype != self::TOK_ID) {
                        $this->error('Block name excepted');
                        return null;
                    }

                    $bname = $this->token;
                    $args = [];

                    if ($this->nextToken()) {
                        if ($this->toktype == self::TOK_OP && $this->token == '(') {
                            do {
                                $this->nextToken();
                                $args[] = $this->infix(1);
                            } while ($this->toktype == self::TOK_OP && $this->token == ',');

                            if ($this->toktype != self::TOK_OP || $this->token != ')') {
                                $this->error('Excepted ")"');
                                return null;
                            }

                            $this->nextToken();
                        }
                    }

                    $val = ['b', $bname, $args];
                    DEBUG('- prefix_end_block');
                    return $this->postfix($lvl, $val);
                } else if ($op == '(') {
                    if (!$this->nextToken()) {
                        $this->error('Argument excepted in "("');
                        return null;
                    }

                    $val = $this->infix(1);

                    if ($this->toktype != self::TOK_OP || $this->token != ')') {
                        $this->error('Excepted ")"');
                        return null;
                    }

                    $this->nextToken();

                    DEBUG('- prefix_end_)');
                    return $this->postfix($lvl, $val);
                } else if ($op == '$') {
                    $gvname = null;

                    $this->nextToken();
                    if ($this->toktype == self::TOK_ID) {
                        $gvname = $this->token;
                        $this->nextToken();
                    }

                    $val = ['g', $gvname];
                    DEBUG('- prefix_end_$');
                    return $this->postfix($lvl, $val);
                } else {
                    $oplvl = $this->findOperator(self::$PRE_OPS, $lvl, $op);
                    if ($oplvl == null) {
                        $this->error('Unexcepted operator "' . $this->token . '"');
                        break;
                    }

                    if (!$this->nextToken()) {
                        $this->error('Unexcepted end of file. Excepted identificator or expression');
                        break;
                    }

                    $val = $this->infix($oplvl[0]);

                    if (is_callable($oplvl[1])) {
                        $val = $oplvl[1]($this, $val, $oplvl[0]);
                    } else {
                        $val = [$oplvl[1] . 'e', $val];
                    }

                    DEBUG('- prefix_end_op');
                    return $this->postfix($lvl, $val);
                }

            case self::TOK_ID:
                switch ($this->token) {
                    case 'true':
                        $res = ['r', true];
                        break;
                    case 'false':
                        $res = ['r', false];
                        break;
                    case 'null':
                        $res = ['r', null];
                        break;
                    default:
                        $res = ['l', $this->token];
                        break;
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
                $this->error('Unknown token (type: ' . $this->toktype . '): "' . $this->token . '"');
                break;
        }

        DEBUG('- prefix_end_null');
        return null;
    }

    public function nextToken()
    {
        switch ($this->state) {
            case self::STATE_TEXT:
                $res = $this->nextToken_text();
                break;

            case self::STATE_CODE:
                $res = $this->nextToken_code();
                break;
        }
        DEBUG('Token: ' . ($res ? '[' . $this->toktype . ', "' . $this->token . '"]' : 'end'));
        return $res;
    }

    public function nextToken_text()
    {
        $this->token = '';

        $cpos = $this->cpos;
        if ($cpos >= $this->ilen) {
            $this->toktype = self::TOK_NONE;
            return false;
        }

        while ($cpos < $this->ilen) {
            $c = $this->input{$cpos};

            if ($c != '{' && $c != '<') {
                if ($c == "\n") {
                    ++$this->cline;
                }
                ++$cpos;
                continue;
            }

            if ($cpos + 1 >= $this->ilen) {
                $cpos = $this->ilen;
                break;
            }

            $c2 = $this->input{$cpos + 1};

            if ($c2 == '*') {
                $this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
                $this->cpos = $cpos + 1;
                $this->nextToken_comment();
                $cpos = $this->cpos;
                continue;
            } else if ($c == '{' && ($c2 == '{' || $c2 == '?') || $c == '<' && $c2 == '<') {
                $this->state = self::STATE_CODE;
                break;
            }

            ++$cpos;
        }

        if ($this->cpos == $cpos) {
            return $this->nextToken_code();
        }
        $this->toktype = self::TOK_INLINE;
        $this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
        $this->cpos = $cpos;

        return true;
    }

    public function nextToken_comment()
    {
        $cpos = $this->cpos;

        while ($cpos < $this->ilen) {
            $c = $this->input{$cpos};

            if ($c != '*') {
                if ($c == "\n") {
                    ++$this->cline;
                }
                ++$cpos;
                continue;
            }

            ++$cpos;

            if ($cpos >= $this->ilen) {
                $cpos = $this->ilen;
                break;
            }

            $c = $this->input{$cpos};
            if ($c == '}') {
                ++$cpos;
                break;
            }
        }

        $this->cpos = $cpos;
    }

    public function nextToken_code()
    {
        $cpos = $this->cpos;

        while ($cpos < $this->ilen && strpos("\r\n\t ", $this->input{$cpos}) !== false) {
            if ($this->input{$cpos} == "\n")
                ++$this->cline;
            ++$cpos;
        }
        $this->cpos = $cpos;

        if ($cpos >= $this->ilen) {
            $this->token = '';
            $this->toktype = self::TOK_NONE;
            return false;
        }

        $ch = $this->input{$cpos};

        if ($ch == '"' || $ch == "'") {
            $esym = $ch;
            ++$cpos;
            $this->cpos = $cpos;
            $this->token = '';

            while ($cpos < $this->ilen) {
                $ch = $this->input{$cpos};
                if ($ch != $esym) {
                    if ($ch == "\n") {
                        ++$this->cline;
                    }

                    if ($ch == '\\' && $cpos + 1 < $this->ilen) {
                        $ch2 = $this->input{$cpos + 1};
                        $rch = '';
                        switch ($ch2) {
                            case 'n':
                                $rch = "\n";
                                break;
                            case 'r':
                                $rch = "\r";
                                break;
                            case 't':
                                $rch = "\t";
                                break;
                            case '\'':
                                $rch = '\'';
                                break;
                            case '"':
                                $rch = '"';
                                break;
                            case '\\':
                                $rch = '\\';
                                break;
                            default:
                                break;
                        }

                        if ($rch) {
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

            if ($cpos >= $this->ilen || $this->input{$cpos} != $esym) {
                $this->error('Excepted ' . $esym);
            }

            $this->toktype = self::TOK_STR;
            $this->token .= substr($this->input, $this->cpos, $cpos - $this->cpos);
            $this->cpos = $cpos + 1;

            return true;
        } else if (strpos(self::OPERATORS, $ch) !== false) {
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

            if ($smres !== false) {
                $this->token = $smres;
                $this->toktype = self::TOK_ESC;

                if ($ch != '{' && $ch != '<' && $ch != ';') {
                    $this->state = self::STATE_TEXT;
                }
                $this->cpos = $cpos + strlen($smres) - 1;

                return true;
            }

            if (strpos(self::TERMINAL_OPERATORS, $ch) === false) {
                while ($cpos < $this->ilen && strpos(self::OPERATORS, $this->input{$cpos}) !== false && strpos(self::TERMINAL_OPERATORS, $this->input{$cpos}) === false)
                    ++$cpos;
            }

            $this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
            $this->cpos = $cpos;

            return true;
        } else if ($ch >= '0' && $ch <= '9') {
            $this->toktype = self::TOK_NUM;

            ++$cpos;
            while ($cpos < $this->ilen) {
                $ch = $this->input{$cpos};
                if ($ch >= '0' && $ch <= '9' || $ch == '.') {
                    ++$cpos;
                } else {
                    break;
                }
            }

            $this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
            $this->cpos = $cpos;

            return true;
        } else if ($ch >= 'a' && $ch <= 'z' || $ch >= 'A' && $ch <= 'Z' || $ch == '_') {
            $this->toktype = self::TOK_ID;

            ++$cpos;
            while ($cpos < $this->ilen) {
                $ch = $this->input{$cpos};
                if ($ch >= 'a' && $ch <= 'z' || $ch >= 'A' && $ch <= 'Z' || $ch >= '0' && $ch <= '9' || $ch == '_') {
                    ++$cpos;
                } else {
                    break;
                }
            }

            $this->token = substr($this->input, $this->cpos, $cpos - $this->cpos);
            $this->cpos = $cpos;

            if (in_array($this->token, self::ID_OPERATORS)) {
                $this->toktype = self::TOK_OP;
            }

            return true;
        } else {
            $this->toktype = self::TOK_UNK;
            $this->token = $ch;
            $this->cpos = $cpos + 1;
            return true;
        }
    }

    public function error($msg)
    {
        throw new \Exception('line ' . $this->cline . ': ' . $msg);
    }

    private function checkNextSM($pos, $m)
    {
        $res = '';
        $state = 0;
        while ($pos < $this->ilen) {
            $ch = $this->input{$pos};
            if (!array_key_exists($ch, $m[$state])) {
                return false;
            }

            $res .= $ch;
            $state = $m[$state][$ch];

            if ($state === false) {
                return false;
            } else if ($state === true) {
                return $res;
            }

            ++$pos;
        }
    }

    public function postfix($lvl, $val)
    {
        DEBUG('+ postfix_call');
        while ($this->toktype == self::TOK_OP) {
            $oplvl = $this->findOperator(self::$POST_OPS, $lvl, $this->token);
            if ($oplvl == null) {
                break;
            }

            if (is_callable($oplvl[1])) {
                $val = $oplvl[1]($this, $val, $oplvl[0]);
            } else {
                $val = [$oplvl[1] . 'p', $val];
                $this->nextToken();
            }

            if ($this->toktype == self::TOK_NONE) {
                break;
            }
        }

        DEBUG('- postfix_end');

        return $val;
    }

    public function findOperator($ops, $lvl, $op)
    {
        DEBUG('search operator: ' . $op . ' ' . $lvl);
        foreach ($ops as $level => $lops) {
            if ($level >= $lvl) {
                foreach ($lops as $opk => $opv) {
                    if (is_callable($opv)) {
                        if ($opk == $op)
                            return [$level, $opv];
                    } else if ($opv == $op) {
                        return [$level, $opv];
                    }
                }
            }
        }

        return null;
    }
}

TemplateLexer::_init();
