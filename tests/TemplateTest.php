<?php
/**
 * Author: maestroprog <maestroprog@gmail.com>
 * License: beerware
 * Use for good
 */

use Iassasin\Phplate\Template;

/**
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateLexer
 */
class TemplateTest extends PHPUnit_Framework_TestCase
{
	public static function setUpBeforeClass(){
		Template::init(__DIR__ . '/resources/', false);
	}

	public function testBuild(){
		$msg = 'Hello World!';
		$this->assertEquals($msg, Template::build('template_test', ['message' => $msg]));
	}

	public function testBuildStr(){
		$msg = 'Hello world!';
		$this->assertEquals($msg, Template::build_str(
			file_get_contents(__DIR__ . '/resources/template_test.html'),
			['message' => $msg]
		));
	}

	public function testPipeFunctions(){
		Template::addUserFunctionHandler('my_pow', function ($v, $args){
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
}
