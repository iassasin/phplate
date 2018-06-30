<?php
/**
 * Created by PhpStorm.
 * User: maestroprog
 * Date: 10.08.17
 * Time: 9:51
 */

namespace Iassasin\Phplate\Tests;

use Iassasin\Phplate\Template;
use Iassasin\Phplate\Exception\PhplateCompilerException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Iassasin\Phplate\TemplateLexer
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateEngine
 * @covers \Iassasin\Phplate\PipeFunctionsContainer
 * @covers \Iassasin\Phplate\TemplateOptions
 * @covers \Iassasin\Phplate\Exception\PhplateException
 * @covers \Iassasin\Phplate\Exception\PhplateCompilerException
 */
class TemplateLexerTest extends TestCase {
	public function testInvalidFuncCall(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ 1| }}', []);
	}

	public function testIncorrectPipeFuncCall(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ 1|safe(1 }}', []);
	}

	public function testIncorrectFuncCall(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ safe(1 }}', []);
	}

	public function testIncorrectArrayIndex(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ this[] }}', []);
	}

	public function testIncorrectArrayOperator(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ var[', []);
	}

	public function testIncorrectArrayOperatorWithIndex(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ var[2', []);
	}

	public function testNotClosedBraces(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{? (1 ?}', []);
	}

	public function testEndOfFileInfixOperator(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{? 1 + ', []);
	}

	public function testEndOfFileForGroupingBrackets(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ (', []);
	}

	public function testIncorrectBlock(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ #', []);
	}

	public function testIncorrectBlockCall(){
		$this->expectException(PhplateCompilerException::class);
		Template::buildStr('{{ #name(23 }}', []);
	}

	/** @expectedException \Iassasin\Phplate\Exception\PhplateCompilerException */
	public function testIncompleteTernaryOperator(){
		Template::buildStr('{{ 1 ? 0 : }}', []);
	}

	/** @expectedException \Iassasin\Phplate\Exception\PhplateCompilerException */
	public function testIncorrectTernaryOperatorAtRight(){
		Template::buildStr('{{ false ? (1 : 3) }}', []);
	}

	/** @expectedException \Iassasin\Phplate\Exception\PhplateCompilerException */
	public function testIncorrectTernaryOperatorAtLeft(){
		Template::buildStr('{{ (false ? 1) : 3 }}', []);
	}

	/** @expectedException \Iassasin\Phplate\Exception\PhplateCompilerException */
	public function testIncorrectTernaryOperator(){
		Template::buildStr('{{ false ? 1 }}', []);
	}
}
