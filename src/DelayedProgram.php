<?php
/**
 * Author: Assasin (iassasin@yandex.ru)
 * License: beerware
 * Use for good
 */

namespace Iassasin\Phplate;

class DelayedProgram {
	private $tpl;
	private $pgm;
	private $values;

	public function __construct(Template $tpl, $pgm, $values){
		$this->tpl = $tpl;
		$this->pgm = $pgm;
		$this->values = $values;
	}

	public function __toString(){
		$oldvals = $this->tpl->values;
		$oldres = $this->tpl->res;

		$this->tpl->values = $this->values;
		$this->tpl->res = '';

		$this->tpl->execPgm($this->pgm);
		$res = $this->tpl->res;

		$this->tpl->values = $oldvals;
		$this->tpl->res = $oldres;

		return $res;
	}
}
