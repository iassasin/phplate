<?php

require_once 'template_base.php';

Template::init($_SERVER['DOCUMENT_ROOT'].'/res/tpl/');
Template::addUserFunctionHandler(function ($v, $func, $fargs){
	$facnt = count($fargs);
	
	switch ($func){
		case 'usercolor':
			$usr = new User(+$v);
			return $usr->getColoredName();
	}
	
	return $v;
});

