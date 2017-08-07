<?php
/**
 * Author: maestroprog <maestroprog@gmail.com>
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate\Tests;

use Iassasin\Phplate\TemplateEngine;
use Iassasin\Phplate\TemplateOptions;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateEngine
 * @covers \Iassasin\Phplate\PipeFunctionsContainer
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateLexer
 * @covers \Iassasin\Phplate\TemplateOptions
 * @covers \Iassasin\Phplate\DelayedProgram
 */
class TemplateIncludeTest extends TestCase {
	public static function setUpBeforeClass(){
		TemplateEngine::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
		);
	}

	public function testInclude(){
		$msg = 'Hello include!';
		$res = TemplateEngine::build('template_with_include', ['data' => ['message' => $msg]]);
		$this->assertEquals($msg, $res);
	}
}
