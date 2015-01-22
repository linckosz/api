<?php

namespace param;

////////////////////////////////////
// FOLDER PERMISSIONS
////////////////////////////////////
/*
cd /path/to/appli
chown -R apache:apache logs
chown -R apache:apache public
*/

////////////////////////////////////
// CALLBACK ORDER
////////////////////////////////////
/*
Before run (no $app | no environment)
MyMiddleware Before (with $app | no environment)
slim.before (with environment)
MyMiddleware After
slim.before.router
slim.before.dispatch
Before render
[render]
After render
slim.after.dispatch
slim.after.router (before buffer rendering)
slim.after (after buffer rendering)
After run
*/

////////////////////////////////////
// DEFAULT SETTING
////////////////////////////////////

//Do not enable debug when we are using json ajax respond
$app->config(array(
	'debug' => false,
	'mode' => 'development',
));

//Create a default class to store special data
$app->lincko = new \stdClass;

//Root directory (which is different from landing page which is in public folder)
$app->lincko->path = $path;

//Insure the the folder is writable by chown apache:apache slim.api/logs and is in share(=writable) path in gluster mode.
//chown apache:apache logs
$app->lincko->logPath = '/glusterfs/.lincko.net/www/share/slim.api/logs';

//Insure the the folder is writable by chown apache:apache slim.api/public and is in share(=writable) path in gluster mode.
//chown apache:apache /path/to/applipublic
$app->lincko->publicPath = '/glusterfs/.lincko.net/www/share/slim.api/public';

//False if we want to use Slim error display, use True for json application
$app->lincko->jsonException = true;

$app->lincko->enableSession = false;

//List all bundles to load (routes are loaded in the order of appearance)
$app->lincko->bundles = array(
	//'bundle name'
	'lincko/api', //Must for back end server
);

//List all middlewares to load in the order of appearance
$app->lincko->middlewares = array_reverse(array(
	//Full path of classes (inside 'middlewares' folder)
	//['bundle name', 'subfolder\class name'],
	['lincko/api', 'JsonApi'],
	['lincko/api', 'CheckAccess'],
));


//List all hooks to load in the order of appearance and priority
$app->lincko->hooks = array(
	//Full path of function (inside 'hooks' folder)
	//['bundle name', 'subfolder\function name', 'the.hook.name', priority value=10],
	['lincko/api', 'SendEmail', 'slim.after', 10],
);

//List all mysql servers to use in master-master (galera cluster) replication configuration
$hosts = array(
	'mariadb1',
);
$app->lincko->hosts = $hosts[array_rand($hosts)];

$app->lincko->databases = array(
	'default' => array(
		'driver' => 'mysql',
		'host' => $app->lincko->hosts,
		'database' => 'lincko_default',
		'username' => 'lincko_default',
		'password' => 'qazwsxedc',
	),
	'sessions' => array(
		'driver' => 'mysql',
		'host' => $app->lincko->hosts,
		'database' => 'lincko_sessions',
		'username' => 'lincko_sessions',
		'password' => 'qazwsxedc',
	),
	'api' => array(
		'driver' => 'mysql',
		'host' => $app->lincko->hosts,
		'database' => 'lincko_api',
		'username' => 'lincko_api',
		'password' => 'qazwsxedc',
	),
);

//Standard value for credential operation only
$app->lincko->security = array(
	'public_key' => 'Bruno Martin', //Value for Sign out
	'private_key' => 'Zhang Xiaorui', //Value for Sign out
	'expired' => '7200', //Expiration time in seconds (2H)
);

//Domain name
$app->lincko->domain = 'lincko.net';

//Application title
$app->lincko->title = 'Lincko';

//Class with email default parameters, it use local Sendmail.postfix function
$app->lincko->email = new \stdClass;
$app->lincko->email->CharSet = 'utf-8';
$app->lincko->email->Abuse = 'abuse@'.$app->lincko->domain;
$app->lincko->email->Sender = 'noreply@'.$app->lincko->domain;
$app->lincko->email->From = 'noreply@'.$app->lincko->domain;
$app->lincko->email->FromName = $app->lincko->title.' server';
$app->lincko->email->List = array();

//Translator parameters
//brunoocto@gmail.com / m*m*3*
$app->lincko->translator = array(
	'client_id' => 'bd0c3bc3-d917-4a1c-810a-74835f62674f',
	'client_secret' => 'q7gZN2eqcX7TJo83+OOgXBc8mQhj2NuaNCYoZGVECZQ=',
);

//Translation list
$app->lincko->translation = array(
	'domain' => $app->lincko->domain,
	'title' => $app->lincko->title,
);

//Some generic data for translation word conversion
$app->lincko->data = array(
	'domain' => $app->lincko->domain,
	'title' => $app->lincko->title,
);


////////////////////////////////////
// BUNDLE lincko/api
////////////////////////////////////

//List of route names accepted without sigining in
$app->lincko->routeFilter = array(
	'user_signin_post',
	'user_create_post',
	'user_signout_get',
	'user_signout_post',
);

//The hook ModifyRequest will redirect to the right class method according to the method requested in the body, it's not HTTP request which is always POST
$app->lincko->method_suffix = '_invalid';
//Input stream (readable one time only; not available for multipart/form-data requests)
$contents = @file_get_contents('php://input');
if($contents && $method = mb_strtolower(json_decode($contents)->method)){
	$app->lincko->method_suffix = '_'.$method;
} else {
	$app->lincko->method_suffix = '_invalid';
}

//Fillin the information about public and private key to the client side
$app->lincko->securityFlash = array();
