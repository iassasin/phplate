<?php
/**
 * Created by PhpStorm.
 * User: maestroprog
 * Date: 10.08.17
 * Time: 9:51
 */

namespace Iassasin\Phplate\Tests;

use Iassasin\Phplate\Template;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Iassasin\Phplate\TemplateLexer
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateEngine
 * @covers \Iassasin\Phplate\PipeFunctionsContainer
 * @covers \Iassasin\Phplate\TemplateOptions
 */
class TemplateLexerTest extends TestCase {
	public function testInvalidFuncCall(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ 1| }}', []);
	}

	public function testIncorrectFuncCall(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ 1|safe(1 }}', []);
	}

	public function testIncorrectArgument(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ this[] }}', []);
	}

	public function testIncorrectArr(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ this[1 }}', []);
	}

	public function testIncorrectFunctionCall(){
		$this->expectException(\Exception::class);
		Template::buildStr('{? (1 ?}', []);
	}
}
