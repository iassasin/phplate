<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

use Iassasin\Phplate\Exception\PhplateException;
use Iassasin\Phplate\Exception\PhplateRuntimeException;
use Iassasin\Phplate\Exception\PhplateCompilerException;

class TemplateEngine {

	private static $instance;

	public $globalVars = [];

	private $tplPath;
	private $options;
	private $userFunctions;
	private $tplCache = [];

	public static function instance(): self {
		return self::$instance ?? self::$instance = new self('./', new TemplateOptions());
	}

	public static function init($tplPath, TemplateOptions $options = null){
		return self::$instance = new self($tplPath, $options ?: new TemplateOptions());
	}

	private function __construct(string $tplPath, TemplateOptions $options){
		$this->tplPath = $tplPath;
		$this->options = $options;
		$this->userFunctions = new PipeFunctionsContainer($options);
	}

	public function getOptions(): TemplateOptions {
		return $this->options;
	}

	public function getUserFunctions(): PipeFunctionsContainer {
		return $this->userFunctions;
	}

	public function addUserFunctionHandler(string $name, callable $f){
		$this->userFunctions->add($name, $f);
	}

	public function addGlobalVar($name, $val){
		$this->globalVars[$name] = $val;
	}

	/**
	 * Вставляет в шаблон $tplName переменные из массива $values
	 * @param string $tplName - имя шаблона
	 * @param array $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 * @return string
	 */
	public function build($tplName, array $values): string {
		$p = $this->compile($tplName);
		if ($p instanceof Template){
			$p = $p->run($values)->getResult();
		}

		return $p;
	}

	/**
	 * Вставляет в шаблон $tplStr переменные из массива $values
	 * @param string $tplStr - код шаблона
	 * @param array $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 * @return string
	 */
	public function buildStr($tplStr, array $values): string {
		$c = new TemplateCompiler($this->options);
		$c->compile($tplStr);
		$p = new Template('', $c->getProgram(), $this->globalVars);
		$p->run($values);

		return $p->getResult();
	}

	/**
	 * Вставляет в шаблон $tplPath переменные из массива $values
	 * @param string $tplPath - путь к файлу шаблона
	 * @param array $values - ассоциативный массив параметров вида ['arg' => 'val'] любой вложенности.
	 * @return string
	 */
	public function buildFile(string $tplPath, array $values): string {
		try {
			$p = $this
				->compileFile($tplPath, $tplPath . '.ctpl')
				->run($values)
				->getResult()
			;
		} catch (PhplateException $e){
			$e->setTemplateLocation($tplPath);
			throw $e;
		}

		return $p;
	}

	public function compile(string $tplName, string $includeFrom = null){
		$tplNameExt = $tplName . '.' . $this->options->getTemplateFileExtension();
		$path = $this->tplPath;
		if (null !== $includeFrom && '' !== $includeFrom && '/' !== $tplName[0]){
			$path = dirname($includeFrom) . '/';
		}
		$tpath = realpath($path . $tplNameExt);

		if ($tpath === false){
			throw new PhplateCompilerException('Template "' . $tplName . '" not found');
		}

		$cachedir = $this->options->getCacheDir();
		if ($cachedir === ''){
			$tcpath = $path . $tplName . '.ctpl';
		} else {
			$tcpath = sprintf('%s/%s-%s.ctpl', $cachedir, basename($tplNameExt), md5($tpath));
		}

		if ($this->options->getCacheEnabled()){
			if (file_exists($tcpath)){
				if (!file_exists($tpath) || filemtime($tcpath) >= filemtime($tpath)){
					$pgm = json_decode(file_get_contents($tcpath), true);
					if (is_array($pgm)){
						$p = new Template($tpath, $pgm, $this->globalVars);
						$this->tplCache[$tpath] = $p;

						return $p;
					}
				}
			}
		}

		try {
			return $this->compileFile($tpath, $tcpath);
		} catch (PhplateException $e){
			$e->setTemplateLocation($tplNameExt);
			throw $e;
		}
	}

	/**
	 * @param string $tplPath
	 * @param string $cachePath
	 * @return Template
	 * @throws PhplateCompilerException
	 */
	protected function compileFile(string $tplPath, string $cachePath): Template {
		if (!file_exists($tplPath)){
			throw new PhplateCompilerException('Template "' . $tplPath . '" not found');
		}
		$p = null;
		if (array_key_exists($tplPath, $this->tplCache)){
			$p = $this->tplCache[$tplPath];
		} else {
			$c = new TemplateCompiler($this->options);
			$c->compile(file_get_contents($tplPath));

			$pgm = $c->getProgram();
			if ($this->options->getCacheEnabled()){
				file_put_contents($cachePath, json_encode($pgm));
			}

			$p = new Template($tplPath, $pgm, $this->globalVars);
			$this->tplCache[$tplPath] = $p;
		}

		return $p;
	}
}
