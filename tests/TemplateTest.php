<?php
/**
 * Author: maestroprog <maestroprog@gmail.com>
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate\Tests;

use Iassasin\Phplate\Template;
use Iassasin\Phplate\TemplateEngine;
use PHPUnit\Framework\TestCase;
use Iassasin\Phplate\TemplateOptions;

/**
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateEngine
 * @covers \Iassasin\Phplate\PipeFunctionsContainer
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateLexer
 * @covers \Iassasin\Phplate\TemplateOptions
 * @covers \Iassasin\Phplate\DelayedProgram
 */
class TemplateTest extends TestCase {
	public static function setUpBeforeClass(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
		);
	}

	public function testBuild(){
		$msg = 'Hello World!';
		$this->assertEquals($msg, Template::build('template_test', ['message' => $msg]));
	}

	public function testBuildStr(){
		$msg = 'Hello world!';
		$this->assertEquals($msg, Template::buildStr(
			file_get_contents(__DIR__ . '/resources/template_test.html'),
			['message' => $msg]
		));
	}

	public function testPipeFunctions(){
		TemplateEngine::instance()->addUserFunctionHandler('my_pow', function ($v, ...$args){
			return pow($v, $args[0]);
		});
		$result = explode('|', Template::build('pipe_test', [
			'arr' => ['Hello' => 'world!', 1 => '2'],
			'slices' => [1, 2, 3, 4],
			'num' => 2,
		]));
		$i = 0;
		$this->assertEquals('&lt;&gt;', $result[$i++]); // safe
		$this->assertEquals("\n<br>Hello\n<br>World!", $result[$i++]); // text
		$this->assertEquals('hello world', $result[$i++]); // lowercase
		$this->assertEquals('HELLO WORLD', $result[$i++]); // uppercase
		$this->assertEquals('%26', $result[$i++]); // urlparam
		$this->assertEquals('{"Hello":"world!","1":"2"}', $result[$i++]); // json
		$this->assertEquals(2, $result[$i++]); // count
		$this->assertEquals('is array', $result[$i++]); // isarray
		$this->assertEquals('Hello,1', $result[$i++]); // join
		$this->assertEquals('1:2', $result[$i++]); // split
		$this->assertEquals('ell', $result[$i++]); // substr(two arguments)
		$this->assertEquals('lo world!', $result[$i++]); // substr(one argument)
		$this->assertEquals('3,4', $result[$i++]); // slice(one argument) & join
		$this->assertEquals('3', $result[$i++]); // slice(two arguments) & join
		$this->assertEquals('Hello hello!', $result[$i++]); // replace
		$this->assertEquals(4, $result[$i++]); // тест работы пользовательской функции my_pow
		$this->assertEquals(16, $i); // сколько тестов должно быть выполнено
	}

	public function testFunctionCall(){
		$res = Template::buildStr('{{ f() }}', ['f' => function (){
			return "no args";
		}]);
		$this->assertEquals('no args', $res);

		$res = Template::buildStr('{{ f(1) }}', ['f' => function ($a){
			return "$a";
		}]);
		$this->assertEquals('1', $res);

		$res = Template::buildStr('{{ f(1, "a", 3.2) }}', ['f' => function ($a, $b, $c){
			return "$a $b $c";
		}]);
		$this->assertEquals('1 a 3.2', $res);
	}

	public function testInlineArrays(){
		$res = Template::buildStr('{{ [5, "ght", 2+2, "world", ]|join("") }}', []);
		$this->assertEquals('5ght4world', $res);

		$res = Template::buildStr(
			'{? for i in [["Boku", "ga"], ["sabishiku"]]; i|join(" ") + " "; end ?}',
			[]
		);
		$this->assertEquals('Boku ga sabishiku ', $res);

		$res = Template::buildStr(
			'{? arr = ["Kono" => "machi", "de" => "ikiteiru"];
				for i in arr|keys; i; " "; arr[i]; " "; end ?}',
			[]
		);
		$this->assertEquals('Kono machi de ikiteiru ', $res);

		$res = Template::buildStr(
			'{? arr = [5 => 10, "y", 7 => 2];
				for i in arr|keys; i; " "; arr[i]; " "; end ?}',
			[]
		);
		$this->assertEquals('5 10 6 y 7 2 ', $res);
	}

	public function testInclude(){
		$msg = 'Hello include!';
		$res = Template::build('template_with_include', ['data' => ['message' => $msg]]);
		$this->assertEquals($msg, $res);
	}
}
