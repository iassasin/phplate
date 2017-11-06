<?php
/**
 * Author: iassasin <iassasin@yandex.ru>
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate\Exception;

class PhplateException extends \Exception {
	protected $origMessage;
	protected $tplLocation;

	public function __construct(string $message = "", int $code = 0, Throwable $previous = NULL){
		parent::__construct($message, $code, $previous);
		$this->origMessage = $message;
		$this->tplLocation = '';
	}

	public function setTemplateLocation(string $loc){
		$this->tplLocation = $loc;
		$this->message = 'Error: ' . $loc . ', ' . $this->message;
	}
}
