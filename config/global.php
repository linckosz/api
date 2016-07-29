<?php

// \time_checkpoint('ok');
function time_checkpoint($msg=''){
	global $app;
	if(isset($app->lincko) && $app->lincko->time_record && class_exists('\\libs\\Watch')){
		$milliseconds = round(microtime(true) * 1000);
		if(is_bool($app->lincko->time_record)){ //initialization
			$app->lincko->time_record = $milliseconds;
			$app->lincko->time_start = $milliseconds;
		}
		$detail = ($milliseconds-$app->lincko->time_record) . "ms to reach this point\n" . ($milliseconds-$app->lincko->time_start)  . "ms in total";
		\libs\Watch::php( $detail , 'Checkpoint: '.$msg, __FILE__, false, false, true);
		$app->lincko->time_record = $milliseconds;
	}
}

function my_autoload($pClassName){
	$app = \Slim\Slim::getInstance();
	$pClassName = str_replace('\\', '/', $pClassName);
	if(file_exists($app->lincko->path.'/'.$pClassName.'.php')){
		include_once($app->lincko->path.'/'.$pClassName.'.php');
	}
}

spl_autoload_register('my_autoload');
