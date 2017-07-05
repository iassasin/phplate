<?php

require_once 'template_base.php';

Template::init($_SERVER['DOCUMENT_ROOT'].'/resources/templates/');
Template::addUserFunctionHandler(function ($v, $func, $fargs){
	$facnt = count($fargs);
	
	switch ($func){
		case 'test':
			return 'test:'.$v.':test';

	}
	
	return $v;
});

