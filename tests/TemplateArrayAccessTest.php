<?php
/**
 * Author: maestroprog <maestroprog@gmail.com>
 * License: beerware
 * Use for good
 */

use Iassasin\Phplate\Template;
use Iassasin\Phplate\TemplateOptions;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateEngine
 * @covers \Iassasin\Phplate\PipeFunctionsContainer
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateLexer
 * @covers \Iassasin\Phplate\TemplateOptions
 */
class TemplateArrayAccessTest extends TestCase {
	public function testArrayAccess(){
		$phplate = Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
		);

		$class = new ArrayClass();
		$class['test'] = 'pass';

		$phplate->addGlobalVar('class', $class);

		$this->assertEquals('pass', $phplate->buildStr('{{ $class["test"] }}', []));

		// Will not work, because ArrayAccess interface does not support get element by reference
		// $this->assertEquals('pass2', $phplate->buildStr('{{ $class["test"] = "pass2"; $class["test"] }}', []));
	}
}

class ArrayClass implements \ArrayAccess {
	private $val;

	public function offsetExists($offset): bool {
		return $offset == 'test';
	}

	public function offsetGet($offset){
		return $offset === 'test' ? $this->val : null;
	}

	public function offsetSet($offset, $value){
		if ($offset === 'test') {
			$this->val = $value;
		}
	}

	public function offsetUnset($offset){

	}
}