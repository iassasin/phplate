<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class TemplateOptions
{
	const OPTION_DATE_FORMAT = 1;
	const OPTION_CACHE_ENABLED = 2;
	const OPTION_AUTO_SAFE = 3;

	protected $options;

	public function getDateFormat(){
		return $this->options[self::OPTION_DATE_FORMAT] ?: 'Y-m-d H:i:s';
	}

	/**
	 * @param string $format
	 * @return self
	 */
	public function setDateFormat($format){
		$date = date($format);
		if (!$date){
			throw new \LogicException("Invalid date format: \"{$format}\"");
		}
		$this->options[self::OPTION_DATE_FORMAT] = $format;

		return $this;
	}

	public function getCacheEnabled(){
		return isset($this->options[self::OPTION_CACHE_ENABLED]) ? $this->options[self::OPTION_CACHE_ENABLED] : true;
	}

	/**
	 * @param bool $enabled
	 * @return self
	 */
	public function setCacheEnabled($enabled){
		if (!is_bool($enabled)){
			throw new \LogicException("Invalid boolean value: \"$enabled\"");
		}
		$this->options[self::OPTION_CACHE_ENABLED] = $enabled;

		return $this;
	}
}
