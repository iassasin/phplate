<?php
/**
 * Created by PhpStorm.
 * User: maestroprog
 * Date: 10.08.17
 * Time: 9:36
 */

namespace Iassasin\Phplate\Tests;

use Iassasin\Phplate\TemplateEngine;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Iassasin\Phplate\Template
 * @covers \Iassasin\Phplate\TemplateEngine
 * @covers \Iassasin\Phplate\TemplateOptions
 * @covers \Iassasin\Phplate\PipeFunctionsContainer
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateLexer
 */
class TemplateEngineTest extends TestCase {
	/**
	 * @var TemplateEngine
	 */
	private static $e;

	public static function setUpBeforeClass(){
		self::$e = TemplateEngine::init(__DIR__ . '/resources/');
		self::$e->getOptions()->setCacheEnabled(false);
		parent::setUpBeforeClass();
	}

	public function testUnknownTemplate(){
		$result = self::$e->build('unknown-template', []);
		$this->assertContains('Error', $result);
	}

	public function testOverridingPipeFunctions(){
		self::$e->addUserFunctionHandler('raw', function (){
			return 'test';
		});
		$this->assertNotEquals('hello', $res = self::$e->buildStr("{{ 'hello'|raw }}", []));
		$this->assertEquals('test', $res);
	}

	public function testInvalidTpl(){
		$result = self::$e->build('invalid_tpl', []);
		$this->assertContains('Error:', $result);
	}
}
