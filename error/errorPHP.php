<?php
//你好 Léo & Luka

namespace error;

use \Exception;
use \libs\Folders;
use \libs\Json;

$app = \Slim\Slim::getInstance();

//Special functions to manage errors
function userErrorHandler($errno, $errmsg, $filename, $linenum, $vars, $type='UNK'){
	/*
	//To avoid "EXH: Error => Exception: SQLSTATE[40001]: Serialization failure: 1213 Deadlock found" we give enough time to the server to retry teh transaction
	if($type=='EXH' && mb_strpos($errmsg, 'try restarting transaction')!==false){
		usleep(300000); //Give 300ms to finish SQL transaction and try again
		return false;
	}
	*/
	//Hide some warnings of exif_read_data because there is a PHP bug if EXIF are not standard
	if($errmsg!="" && (mb_strpos($errmsg, 'exif_read_data')===false || mb_strpos($errmsg, 'Illegal')===false)){
		$app = \Slim\Slim::getInstance();
		$logPath = $app->lincko->logPath.'/php';
		$dt = date("Y-m-d H:i:s (T)");
		$infos = $app->request->getUserAgent();
		$errortype = array(
			E_WARNING            => 'Warning',
			E_NOTICE             => 'Notice',
			E_USER_ERROR         => 'User Error',
			E_USER_WARNING       => 'User Warning',
			E_USER_NOTICE        => 'User Notice',
			E_STRICT             => 'Runtime Notice',
			E_RECOVERABLE_ERROR  => 'Catchable Fatal Error',
			//The list below is not captures by this function
			E_ERROR              => 'Error',
			E_PARSE              => 'Parsing Error',
			E_CORE_ERROR         => 'Core Error',
			E_CORE_WARNING       => 'Core Warning',
			E_COMPILE_ERROR      => 'Compile Error',
			E_COMPILE_WARNING    => 'Compile Warning',
			1     => 'Error',
			2     => 'Warning',
			4     => 'Parsing Error',
			8     => 'Notice',
			16    => 'Core Error',
			32    => 'Core Warning',
			64    => 'Compile Error',
			128   => 'Compile Warning',
			256   => 'User Error',
			512   => 'User Warning',
			1024  => 'User Notice',
			2048  => 'Runtime Notice',
			4096  => 'Catchable Fatal Error',
			8192  => 'Depreciated',
			16384 => 'User Depreciated',
			32767 => 'All'
		);
		
		$errid = 'unknown'; //User ID
		$erruser = 'unknown'; //User Login
		if( isset($app->lincko) && isset($app->lincko->data) ){
			if(isset($app->lincko->data['uid'])){
				$errid = $app->lincko->data['uid'];
			}
			if(isset($app->lincko->data['yonghu'])){
				$erruser = $app->lincko->data['yonghu'];
			}
		}
		
		if(isset($_SERVER) && isset($_SERVER['REMOTE_ADDR'])){
			$errip = $_SERVER['REMOTE_ADDR'];
		} else {
			$errip = $app->request->getIp();
		}

		if(is_array($vars) || is_object($vars)){
			$var = (string)print_r($vars,true);
		} else {
			$var = (string)$vars;
		}

		$url = $app->request->headers->Host.$app->request->getResourceUri();
		
		$err  = "DATE: $dt\n";
		$err .= "USER: $errid / $erruser / $errip\n";
		$err .= "BROW: $infos\n";
		$err .= "LINE: $linenum\n";
		$err .= "FILE : $filename\n";
		$err .= "URL : $url\n";
		$err .= "MSG : $type: $errortype[$errno] => $errmsg\n";
		$err .= "DBT : $var\n\n\n";

$err = str_replace("\n","
",$err);

		$folder = new Folders;
		$folder->createPath($logPath, 0770);

		$fic = $logPath.'/logPHP_'.date('ymd').'.txt';
		if(file_exists($fic)){
			if(filesize($fic)>1000000){ //Help to never get a file higher than 1MB, avoid to fulfill the server space in case of heavy bug
				if($fp = fopen($fic, 'r+')){ //We open the file in read/write, and place the cursor at the beginning
					@ftruncate($fp,500000); //Cut the file in half (like that it keep all first alerts)
					fclose($fp); //CLose the file
				}
			}
		}

		error_log($err, 3, $fic);
		//Do not do a return, we cannot send information to InfoDebug

		sendMsg();
	}
}

function shutdownHandler(){
	$lasterror = error_get_last();
	$exception = new Exception();
	$dbt = getTraceAsString($exception, 10);
	userErrorHandler($lasterror['type'], $lasterror['message'], $lasterror['file'], $lasterror['line'], $dbt, 'SDH');
}

function exceptionHandler(\Throwable $exception) {
	$dbt = getTraceAsString($exception, 10);
	userErrorHandler(E_ERROR, 'Exception: '.$exception->getMessage(), $exception->getFile(), $exception->getLine(), $dbt, 'EXH');
}

function sendMsg(){
	$app = \Slim\Slim::getInstance();
	//$msg = 'An error has occurred while processing your request, the support team has been notified of the problem.';
	$msg = $app->trans->getBRUT('default', 1, 2);
	if($app->lincko->jsonException){
		$json = new Json($msg, true, 500, false, false, array(), $app->lincko->showError);
		$json->render(500);
	} else {
		echo $msg;
	}
	return exit(0);
}

//http://php.net/manual/fr/function.debug-backtrace.php
//$trace = \error\getTraceAsString(new \Exception, 30);
function getTraceAsString($e, $count=0){
	$trace = explode("\n", $e->getTraceAsString());
	array_shift($trace); // remove call to this method
	array_pop($trace); // remove {main}
	$length = count($trace);
	if($count > $length){ $count = $length; } //Get maximum of information
	$result = array();
	for ($i = 0; $i < $count; $i++){
		$result[] = ($i + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
	}
	return "Debug backtrace on $count lines\n\t" . implode("\n\t", $result);
}

//Start PHP error monitoring
set_error_handler('error\userErrorHandler');
register_shutdown_function('error\shutdownHandler');
set_exception_handler('error\exceptionHandler');

$app->error(function (\Throwable $exception) use ($app) {
	exceptionHandler($exception);
});
