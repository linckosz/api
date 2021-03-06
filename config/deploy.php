<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\TranslationModel;
use \libs\Datassl;
use \libs\Folders;
use \libs\Version;

$path = dirname(__FILE__).'/..';

require_once $path.'/vendor/autoload.php';

$app = new \Slim\Slim();

require_once $path.'/config/global.php';
require_once $path.'/config/language.php';
require_once $path.'/param/common.php';
require_once $path.'/param/unique/parameters.php';

$app->config(array(
	'log.enable' => false,
));
ini_set('display_errors', '1');
ini_set('opcache.enable', '0');

require_once $path.'/error/errorPHP.php';
require_once $path.'/config/eloquent.php';

$app->get('/get/:ip/:hostname/:deployment/:sub/:git', function ($ip = null, $hostname = null, $deployment = null, $sub = null, $git = null) use ($app) {

	$version = Version::find(1);
	if(!$version){
		$version = new Version;
		$version->id = 1;
	}
	$version->version = $git; //We get back the last commit md5
	$version->save();

	$list = array();
	foreach ($app->lincko->databases as $bundle => $value) {
		if(Capsule::schema($bundle)->hasTable('translation')){
			$list[$bundle] = TranslationModel::on($bundle)->get()->toArray();
		}
	}

	$domain = $_SERVER['HTTP_HOST'];
	if(strpos($domain, ':')){
		$domain = strstr($domain, ':', true);
	}
	if(!preg_match("/^([a-z]+).(lincko.\w+)$/ui", $domain)){
		echo "It has to use a hostname qualified\n";
		return true;
	}
	if( !password_verify($deployment, '$2y$10$J6gakNmqkjrpnyMFJHhyq.JQves6JslSHJLKqpWXfZVJ6qpDKDXK6') ){
		echo "You are not authorized to modify the translation database\n";
		return true;
	}
	echo "Get the translation data [$domain]\n";

	$data = json_encode(array(
		'translation' => $list,
		'deployment' => $deployment,
		'git' => $git,
	));
	$ch = curl_init($ip.':8888/update');
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);
	curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json; charset=UTF-8',
			'Content-Length: ' . mb_strlen($data),
			'Host: '.$sub.'.'.$hostname,
		)
	);

	$verbose = fopen('php://temp', 'w+');
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_STDERR, $verbose);

	if($result = curl_exec($ch)){
		echo $result;
	} else {
		echo "cURL error!\n";
		\libs\Watch::php(curl_getinfo($ch), '$ch', __FILE__, __LINE__, false, false, true);
		$error = '['.curl_errno($ch)."] => ".htmlspecialchars(curl_error($ch));
		\libs\Watch::php($error, '$error', __FILE__, __LINE__, false, false, true);
		rewind($verbose);
		\libs\Watch::php(stream_get_contents($verbose), '$verbose', __FILE__, __LINE__, false, false, true);
		fclose($verbose);
	}
	
	@curl_close($ch);

	echo "DONE\n";
})
->conditions(array(
	'ip' => '(?:[0-9]{1,3}\.){3}[0-9]{1,3}',
	'hostname' => '([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}',
	'deployment' => '\w+',
	'sub' => '\w+',
	'git' => '\w+',
))
->name('get_translation_data');

$app->post('/update', function () use ($app) {
	$domain = $_SERVER['HTTP_HOST'];
	if(strpos($domain, ':')){
		$domain = strstr($domain, ':', true);
	}
	if(!preg_match("/^([a-z]+).(lincko.\w+)$/ui", $domain)){
		echo "It has to use a hostname qualified\n";
		return true;
	}
	$data = json_decode($app->request->getBody());
	$app->lincko->deployment = $data->deployment;
	if( !password_verify($app->lincko->deployment, '$2y$10$J6gakNmqkjrpnyMFJHhyq.JQves6JslSHJLKqpWXfZVJ6qpDKDXK6') ){
		echo "You are not authorized to modify the translation database\n";
		return true;
	}
	echo "Update the translation data [$domain] => \n";
	$translation = $data->translation;
	foreach ($translation as $bundle => $items) {
		foreach ($items as $item) {
			if($sentence = TranslationModel::on($bundle)->where('category', $item->category)->where('phrase', $item->phrase)->first()){
				//If The sentence already exists
				foreach ($item as $key => $attribute) {
					$sentence->$key = $attribute;
				}
				$dirty = $sentence->getDirty();
				if(count($dirty) > 0){
					foreach ($dirty as $value) {
						if($value){ //Check that there is a value inside the 
							$original = $sentence->getOriginal();
							if($sentence->querySave()){
								$str_dirty = preg_replace( "/\r|\n/", "\\n", json_encode($dirty, JSON_UNESCAPED_UNICODE) );
								$str_original = preg_replace( "/\r|\n/", "\\n", json_encode($original, JSON_UNESCAPED_UNICODE) );
								echo "  - [FROM]: $str_original\n";
								echo "  - [ TO ]: $str_dirty\n\n";
							}
							break;
						}
					}
				}
			} else {
				//New sentence
				if(TranslationModel::queryInsert($bundle, $item)){
					$str_new = preg_replace( "/\r|\n/", "\\n", json_encode($item, JSON_UNESCAPED_UNICODE) );
					echo "  - {NEW} : $str_new\n\n";
				}
			}
		}
	}

	$version = Version::find(1);
	if(!$version){
		$version = new Version;
		$version->id = 1;
	}
	$version->version = $data->git;
	$version->save();
	
	echo "Translation ok\n";
})
->name('update_translation_data');

$app->map('/:catchall', function() use ($app) {
	echo 'Page not found';
})->conditions(array('catchall' => '.*'))
->name('catchall')
->via('GET', 'POST', 'PUT', 'DELETE');

$app->run();
//Checking $app (print_r) after run can make php crashed out of memory because it contains files data
