<?php

namespace config;

use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule();

foreach($app->lincko->databases as $key => $database) {
	$capsule->addConnection(array(
		'driver' => $database['driver'],
		'host' => $database['host'],
		'database' => $database['database'],
		'username' => $database['username'],
		'password' => $database['password'],
		'charset'   => 'utf8mb4',
		'collation' => 'utf8mb4_unicode_ci',
		'prefix' => '',
	), $key);
	//$db = Capsule::connection($key);
	//\libs\Watch::php($key, '$db', __FILE__, false, false, true);
}

//Erase connection information to avoid hacking
foreach($app->lincko->databases as $key => $database) {
	$app->lincko->databases[$key]['driver'] = '******';
	$app->lincko->databases[$key]['host'] = '******';
	$app->lincko->databases[$key]['database'] = '******';
	$app->lincko->databases[$key]['username'] = '******';
	$app->lincko->databases[$key]['password'] = '******';
}

$capsule->setAsGlobal();
$capsule->bootEloquent();
