<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class TemplateOptions
{
	const OPTION_DATE_FORMAT = 'date_format';

	protected $options;

	public function getDateFormat(){
		return $this->options[self::OPTION_DATE_FORMAT] ?: 'H:i Y-m-d';
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
}
