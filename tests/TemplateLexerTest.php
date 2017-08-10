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

	public function testIncorrectFuncCall2(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ safe(1 }}', []);
	}

	public function testIncorrectArgument(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ this[] }}', []);
	}

	public function testIncorrectArr(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ var[', []);
	}

	public function testIncorrectArr2(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ var[2', []);
	}

	public function testNotClosedBraces(){
		$this->expectException(\Exception::class);
		Template::buildStr('{? (1 ?}', []);
	}

	public function testEndOfFile(){
		$this->expectException(\Exception::class);
		Template::buildStr('{? 1 + ', []);
	}

	public function testEndOfFile2(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ (', []);
	}

	public function testFailBlock(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ #', []);
	}

	public function testFailBlock2(){
		$this->expectException(\Exception::class);
		Template::buildStr('{{ #name(23 }}', []);
	}
}
