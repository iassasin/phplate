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
class TemplateCacheTest extends TestCase {
	public static function setUpBeforeClass(){
		$file = __DIR__ . '/resources/template_test.ctpl';
		if (file_exists($file)){
			unlink($file);
		}
	}

	public static function tearDownAfterClass(){
		$file = __DIR__ . '/resources/template_test.ctpl';
		if (file_exists($file)){
			unlink($file);
		}
	}

	public function testCacheEnabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(true)
			->setAutoSafeEnabled(true)
		);

		$this->assertEquals('msg', Template::build('template_test', ['message' => 'msg']));
		$this->assertEquals('message', Template::build('template_test', ['message' => 'message']));
	}

	public function testCacheDisabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setAutoSafeEnabled(false)
		);

		$this->assertEquals('msg', Template::build('template_test', ['message' => 'msg']));
		$this->assertEquals('message', Template::build('template_test', ['message' => 'message']));
	}
}
