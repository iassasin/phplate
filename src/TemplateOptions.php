<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class TemplateOptions {
	const OPTION_DATE_FORMAT = 1;
	const OPTION_CACHE_ENABLED = 2;
	const OPTION_AUTO_SAFE = 3;
	const OPTION_CACHE_DIR = 5;

	protected $options = [];

	public function __construct(){
		$this->options[self::OPTION_DATE_FORMAT] = 'Y-m-d H:i:s';
		$this->options[self::OPTION_CACHE_ENABLED] = true;
		$this->options[self::OPTION_AUTO_SAFE] = true;
		$this->options[self::OPTION_CACHE_DIR] = getcwd();
	}

	public function getDateFormat(): string {
		return $this->options[self::OPTION_DATE_FORMAT];
	}

	/**
	 * @param string $format
	 * @return self
	 */
	public function setDateFormat(string $format): self {
		$date = date($format);
		if (!$date){
			throw new \LogicException("Invalid date format: \"{$format}\"");
		}
		$this->options[self::OPTION_DATE_FORMAT] = $format;

		return $this;
	}

	public function getCacheEnabled(): bool {
		return $this->options[self::OPTION_CACHE_ENABLED];
	}

	/**
	 * @param bool $enabled
	 * @return self
	 */
	public function setCacheEnabled(bool $enabled): self {
		$this->options[self::OPTION_CACHE_ENABLED] = $enabled;

		return $this;
	}

	public function getAutoSafeEnabled(): bool {
		return $this->options[self::OPTION_AUTO_SAFE];
	}

	/**
	 * @param bool $enabled
	 * @return self
	 */
	public function setAutoSafeEnabled(bool $enabled): self {
		$this->options[self::OPTION_AUTO_SAFE] = $enabled;

		return $this;
	}

	/**
	 * @return string path without ending "/"
	 */
	public function getCacheDir(): string {
		return rtrim($this->options[self::OPTION_CACHE_DIR], '/');
	}

	/**
	 * @param string $dir
	 * @return TemplateOptions
	 * @throws \InvalidArgumentException
	 */
	public function setCacheDir(string $dir): self {
		if (!file_exists($dir) || !is_dir($dir)) {
			throw new \InvalidArgumentException('Invalid cache directory "' . $dir . '": directory does not exists.');
		}
		$this->options[self::OPTION_CACHE_DIR] = $dir;

		return $this;
	}
}
