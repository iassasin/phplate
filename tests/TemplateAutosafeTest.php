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
class TemplateAutosafeTest extends TestCase {
	public function testAutoSafeEnabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setAutoSafeEnabled(true)
		);

		$this->assertEquals('<&lt;>', Template::build_str('<{{ val }}>', ['val' => '<']));
		$this->assertEquals('<<>', Template::build_str('<{{ val|raw }}>', ['val' => '<']));
		$this->assertEquals('<&lt;>', Template::build_str('<{{ "<"|safe }}>', []));
		// проверяем, что при применении другой пайп-функции экранирование работает
		$this->assertEquals('<&lt;>', Template::build_str('<{{ "<"|lowercase }}>', []));
		$this->assertEquals('<&lt;&nbsp;&nbsp;>', Template::build_str('<{{ "<  "|text }}>', []));
	}

	public function testAutoSafeDisabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setAutoSafeEnabled(false)
		);

		$this->assertEquals('<<>', Template::build_str('<{{ val }}>', ['val' => '<']));
		$this->assertEquals('<<>', Template::build_str('<{{ val|raw }}>', ['val' => '<']));
		$this->assertEquals('<&lt;>', Template::build_str('<{{ val|safe }}>', ['val' => '<']));
	}
}
