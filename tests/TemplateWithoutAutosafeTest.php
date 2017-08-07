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
 * @covers \Iassasin\Phplate\TemplateCompiler
 * @covers \Iassasin\Phplate\TemplateLexer
 * @covers \Iassasin\Phplate\TemplateOptions
 */
class TemplateWithoutAutosafeTest extends TestCase {
	public function setUp(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setAutoSafeEnabled(false)
		);
	}

	public function testAutoSafeDisabled(){
		// проверяем выключенное автоэкранирование
		$this->assertEquals('<<>', Template::build_str('<{{ val }}>', ['val' => '<']));
		// использование принудительного экранирования
		$this->assertEquals('<&lt;>', Template::build_str('<{{ val|safe }}>', ['val' => '<']));
	}
}
