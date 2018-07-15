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
	private static $cacheFileWin1251NoCacheDir = __DIR__ . '/resources/win1251.ctpl';
	private static $cacheFileNoCacheDir = __DIR__ . '/resources/template_test.ctpl';
	private static $cacheFileInCacheDir;

	private static function cleanFile($file){
		if (file_exists($file)){
			unlink($file);
		}
	}

	private static function cleanCaches(){
		self::cleanFile(self::$cacheFileNoCacheDir);
		self::cleanFile(self::$cacheFileInCacheDir);
		self::cleanFile(self::$cacheFileWin1251NoCacheDir);
	}

	public static function setUpBeforeClass(){
		self::$cacheFileInCacheDir = __DIR__ . '/resources/cache/template_test.html-'
			.md5(realpath(__DIR__ . '/resources/template_test.html')) .'.ctpl';
		self::cleanCaches();
	}

	public static function tearDownAfterClass(){
		self::cleanCaches();
	}

	public function testCacheEnabledNoDir(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(true)
			->setCacheDir('')
			->setTemplateFileExtension('html')
			->setAutoSafeEnabled(true)
		);

		self::cleanCaches();

		$this->assertEquals('msg', Template::build('template_test', ['message' => 'msg']));
		$this->assertTrue(file_exists(self::$cacheFileNoCacheDir));
		$this->assertFalse(file_exists(self::$cacheFileInCacheDir));

		$this->assertEquals('message', Template::build('template_test', ['message' => 'message']));
		$this->assertTrue(file_exists(self::$cacheFileNoCacheDir));
		$this->assertFalse(file_exists(self::$cacheFileInCacheDir));
	}

	public function testCacheEnabledWithDir(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(true)
			->setCacheDir(__DIR__ . '/resources/cache')
			->setTemplateFileExtension('html')
			->setAutoSafeEnabled(true)
		);

		self::cleanCaches();

		$this->assertEquals('msg', Template::build('template_test', ['message' => 'msg']));
		$this->assertFalse(file_exists(self::$cacheFileNoCacheDir));
		$this->assertTrue(file_exists(self::$cacheFileInCacheDir));

		$this->assertEquals('message', Template::build('template_test', ['message' => 'message']));
		$this->assertFalse(file_exists(self::$cacheFileNoCacheDir));
		$this->assertTrue(file_exists(self::$cacheFileInCacheDir));
	}

	public function testCacheDisabled(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(false)
			->setTemplateFileExtension('html')
			->setAutoSafeEnabled(false)
		);

		self::cleanCaches();

		$this->assertEquals('msg', Template::build('template_test', ['message' => 'msg']));
		$this->assertFalse(file_exists(self::$cacheFileNoCacheDir) || file_exists(self::$cacheFileInCacheDir));
		$this->assertEquals('message', Template::build('template_test', ['message' => 'message']));
		$this->assertFalse(file_exists(self::$cacheFileNoCacheDir) || file_exists(self::$cacheFileInCacheDir));
	}

	public function testCacheEnabledInvalidCacheFile(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(true)
			->setCacheDir('')
			->setTemplateFileExtension('html')
			->setAutoSafeEnabled(true)
		);

		self::cleanCaches();

		file_put_contents(self::$cacheFileNoCacheDir, '');

		$this->assertEquals('msg', Template::build('template_test', ['message' => 'msg']));
		$this->assertTrue(file_exists(self::$cacheFileNoCacheDir));
		$this->assertFalse(file_exists(self::$cacheFileInCacheDir));

		$this->assertEquals('message', Template::build('template_test', ['message' => 'message']));
		$this->assertTrue(file_exists(self::$cacheFileNoCacheDir));
		$this->assertFalse(file_exists(self::$cacheFileInCacheDir));
	}

	public function testCacheEnabledWin1251Tpl(){
		Template::init(__DIR__ . '/resources/', (new TemplateOptions())
			->setCacheEnabled(true)
			->setCacheDir('')
			->setTemplateFileExtension('html')
			->setAutoSafeEnabled(true)
		);

		self::cleanCaches();

		file_put_contents(self::$cacheFileNoCacheDir, '');

		$this->assertEquals(mb_convert_encoding('русские символы', 'CP1251'), Template::build('win1251', []));
		$this->assertTrue(file_exists(self::$cacheFileWin1251NoCacheDir));
		$this->assertTrue(filesize(self::$cacheFileWin1251NoCacheDir) > 0);

		$this->assertEquals(mb_convert_encoding('русские символы', 'CP1251'), Template::build('win1251', []));
		$this->assertTrue(file_exists(self::$cacheFileWin1251NoCacheDir));
		$this->assertTrue(filesize(self::$cacheFileWin1251NoCacheDir) > 0);
	}
}
