<?php

require_once 'template_base.php';

Template::init($_SERVER['DOCUMENT_ROOT'].'/res/tpl/');
Template::addUserFunctionHandler(function ($v, $func, $fargs){
	$facnt = count($fargs);
	
	switch ($func){
		case 'usercolor':
			$usr = new User(+$v);
			return $usr->getColoredName();
		
		case 'humanbytes':
			return translateBytes(+$v);
			
		case 'build_pagination':
			$cur = $fargs[0];
			$count = $v;
			$CNT = $fargs[1];
			
			$res = [1];
			$i = 1;
			if ($i < $count){
				if ($i < $cur - $CNT)
					$res[] = '...';
		
				for ($i = max($i, $cur - $CNT); $i < $count - 1 && $i < $cur + $CNT + 1; ++$i){
					$res[] = $i+1;
				}
	
				if ($i < $count - 1){
					$res[] = '...';
				}
				
				$res[] = $count;
			}

			return $res;
			
	}
	
	return $v;
});

