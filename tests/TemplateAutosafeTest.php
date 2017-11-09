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
class TemplateAutosafeTest extends TestCase {
	public function testAutoSafeEnabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setAutoSafeEnabled(true)
		);

		$this->assertEquals('<&lt;>', Template::buildStr('<{{ val }}>', ['val' => '<']));
		$this->assertEquals('<<>', Template::buildStr('<{{ val|raw }}>', ['val' => '<']));
		$this->assertEquals('<&lt;>', Template::buildStr('<{{ "<"|safe }}>', []));
		// проверяем, что при применении другой пайп-функции экранирование работает
		$this->assertEquals('<&lt;>', Template::buildStr('<{{ "<"|lowercase }}>', []));
		$this->assertEquals('<&lt;&nbsp;&nbsp;>', Template::buildStr('<{{ "<  "|text }}>', []));
	}

	public function testAutoSafeDisabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setAutoSafeEnabled(false)
		);

		$this->assertEquals('<<>', Template::buildStr('<{{ val }}>', ['val' => '<']));
		$this->assertEquals('<<>', Template::buildStr('<{{ val|raw }}>', ['val' => '<']));
		$this->assertEquals('<&lt;>', Template::buildStr('<{{ val|safe }}>', ['val' => '<']));
	}
}
