<?php
/**
 * Author: maestroprog <maestroprog@gmail.com>
 * License: beerware
 * Use for good
 */

use Iassasin\Phplate\TemplateOptions;
use Iassasin\Phplate\Exception\PhplateConfigException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Iassasin\Phplate\TemplateOptions
 * @covers \Iassasin\Phplate\Exception\PhplateException
 * @covers \Iassasin\Phplate\Exception\PhplateConfigException
 */
class TemplatOptionsTest extends TestCase {
	public function testTemplateOptions(){
		$opt = (new TemplateOptions())
			->setCacheEnabled(true)
			->setAutoSafeEnabled(true)
			->setDateFormat('[Y-m-d H:i:s]')
		;

		$this->assertEquals(true, $opt->getCacheEnabled());
		$this->assertEquals(true, $opt->getAutoSafeEnabled());
		$this->assertEquals('[Y-m-d H:i:s]', $opt->getDateFormat());
	}

	public function testInvalidOptionDate(){
		$this->expectException(PhplateConfigException::class);
		(new TemplateOptions())->setDateFormat('0');
	}
}
