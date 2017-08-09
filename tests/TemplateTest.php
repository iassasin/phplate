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
		$time = time();
		Template::addUserFunctionHandler('my_pow', function ($v, ...$args){
			return pow($v, $args[0]);
		});
		$result = explode('|', Template::build('pipe_test', [
			'arr' => ['Hello' => 'world!', 1 => '2'],
			'slices' => [1, 2, 3, 4],
			'num' => 2,
			'time' => $time,
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
		$this->assertEquals(date('Y-m-d H:i:s', $time), $result[$i++]); // тест работы пользовательской функции my_pow
		$this->assertEquals(17, $i); // сколько тестов должно быть выполнено
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
		$res = Template::buildStr('{? include template_test data ?}', ['data' => ['message' => $msg]]);
		$this->assertEquals($msg, $res);
	}

	public function testAssignment(){
		$this->assertEquals('5', Template::buildStr('{? v = 5; v ?}', []));
		$this->assertEquals('5', Template::buildStr('{? v.f = 5; v.f ?}', ['v' => []]));
		$this->assertEquals('string', Template::buildStr('{? v.f = "string"; v.f ?}', ['v' => []]));
		$this->assertEquals('s,1,val', Template::buildStr(
			'{? v.f = []; v.f.arr = ["s", 1, "val"]; v.f.arr|join(",") ?}',
			['v' => []]
		));
	}

	public function testBlocks(){
		$this->assertEquals('', Template::buildStr('{? #invalid ?}', []));
		$this->assertEquals('test', Template::buildStr(
			'{? block tb; "test" end; #tb ?}',
			[]
		));
		$this->assertEquals('012', Template::buildStr(
			'{? block tb; this[0]; this[1]; this[2] end; #tb(0, 1, 2) ?}',
			[]
		));
	}

	public function testGlobalVars(){
		Template::addGlobalVar('gvar', ['key1' => 'val1', 'key2' => 2]);
		$this->assertEquals('val12', Template::buildStr('{{ $gvar.key1 + $gvar.key2 }}', []));
	}

	public function testComments(){
		$this->assertEquals('value', Template::buildStr('v{* test {{t}} *}alue', ['t' => 'test']));
	}

	public function testStrings(){
		$this->assertEquals("\n \r \t ' &quot; \\ \\h", Template::buildStr('{{ "\n \r \t \\\' \" \\\\ \h" }}', []));
		$this->assertEquals("\n \r \t ' &quot; \\ \\h", Template::buildStr('{{ \'\n \r \t \\\' \" \\\\ \h\' }}', []));
	}

	public function testOperators(){
		$this->assertEquals('2', Template::buildStr('{{ +2 }}', []));
		$this->assertEquals('0', Template::buildStr('{{ 2-2 }}', []));
		$this->assertEquals('1.2', Template::buildStr('{{ 12/10 }}', []));

		$this->assertEquals('1', Template::buildStr('{? if 2 == "2" and 1 != "3"; 1; end ?}', []));
		$this->assertEquals('1', Template::buildStr('{? if 2 === 2 and 2 !== "2"; 1; end ?}', []));
		$this->assertEquals('1', Template::buildStr(
			'{? if 3 > 2 and 2 >= 2 and 2 < 3 and 2 <= 3; 1; end ?}',
			[]
		));
		$this->assertEquals('1', Template::buildStr('{? if false or true; 1; end ?}', []));
		$this->assertEquals('7', Template::buildStr('{{ 3 xor 4 }}', []));

		$this->assertEquals('val', Template::buildStr('{{ false ?? "val" }}', []));
		$this->assertEquals('val', Template::buildStr('{{ "val" ?? "fail" }}', []));

		$this->assertEquals('5', Template::buildStr('{{ i = 0; i += 5; i }}', []));
		$this->assertEquals('-5', Template::buildStr('{{ i = 0; i -= 5; i }}', []));
		$this->assertEquals('6', Template::buildStr('{{ i = 2; i *= 3; i }}', []));
		$this->assertEquals('2', Template::buildStr('{{ i = 6; i /= 3; i }}', []));

		$this->assertEquals('6', Template::buildStr('{{ 2+2*2 }}', []));
		$this->assertEquals('8', Template::buildStr('{{ (2+2)*2 }}', []));
		$this->assertEquals('8', Template::buildStr('{{ -(2+2) * -2 }}', []));
		$this->assertEquals('true', Template::buildStr(
			'{? if t === true and f === false and n === null; "true" else "false" end ?}',
			['t' => true, 'f' => false, 'n' => null]
		));
	}

	public function testWidgetUse(){
		$res = Template::buildStr(
			'{? widget hello ?}{? widget world ?}{{ body }}{? end ?}{{ attrs.pre; body }}{? end ?}<<hello pre="> ">>Hello <<world>>world!<</world>><</hello>>',
			['hello' => 'Hello', 'world' => 'world']
		);
		$this->assertEquals('&gt; Hello world!', $res);
	}

	public function testConstructionIf(){
		$this->assertEquals('1', Template::buildStr(
			'{? if false; 0; end; if true; 1; end ?}',
			[]
		));
		$this->assertEquals('2', Template::buildStr(
			'{? if false; 1; else if true; 2; end ?}',
			[]
		));
	}

	public function testConstructionFor(){
		$this->assertEquals('123', Template::buildStr(
			'{? for v in [1, 2, 3]; v; end ?}',
			[]
		));
		$this->assertEquals('12345', Template::buildStr(
			'{? for v = 1 while v < 6 next v += 1; v; end ?}',
			[]
		));
	}

	public function testObjectAccess(){
		$obj = new class {
			public $field = 'fld';
			public function f($a, $b, $c){ return "$a $b $c"; }
		};

		$this->assertEquals('1 2 3', Template::buildStr(
			'{{ o.f(1, 2, 3) }}',
			['o' => $obj]
		));

		$this->assertEquals('1 2 3', Template::buildStr(
			'{{ o["f"](1, 2, 3) }}',
			['o' => $obj]
		));

		$this->assertEquals('fld', Template::buildStr('{{ o.field }}', ['o' => $obj]));
		$this->assertEquals('fld', Template::buildStr('{{ o["field"] }}', ['o' => $obj]));
	}

}
