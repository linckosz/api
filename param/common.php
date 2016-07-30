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

//Create a default class to store special data
$app->lincko = new \stdClass;

//Used to track operation time
$app->lincko->time_record = false;
$app->lincko->time_start = 0;

//Application title
$app->lincko->title = 'Lincko';

//at True it forces a HTTP code response to 200, mainly used to received feedbacks for file posting
$app->lincko->http_code_ok = false;

//Domain name
if(isset($_SERVER["SERVER_HOST"])){
	$app->lincko->domain = $_SERVER["SERVER_HOST"];
} else if(strpos($_SERVER["HTTP_HOST"], ':')){
	$app->lincko->domain = strstr($_SERVER["HTTP_HOST"], ':', true);
} else {
	$app->lincko->domain = $_SERVER["HTTP_HOST"];
}

$app->lincko->domain_restriction = "/^(?:.{1,3}|(?:api|cloud|dc|file|info|lincko|mail|mx|ns|pop|smtp|tp|debug|www)\d*)$/ui";

//Do not enable debug when we are using json ajax respond
$app->config(array(
	'debug' => false,
	'mode' => 'production',
	'cookies.encrypt' => true, //Must use $app->getCookie('foo', false);
	'cookies.secret_key' => 'au6G7dbSh87Ws',
	'cookies.lifetime' => '365 days',
	'cookies.secure' => true,
	'cookies.path' => '/',
	'cookies.httponly' => true,
	'templates.path' => '..',
	'debug' => false,
));

//Root directory (which is different from landing page which is in public folder)
$app->lincko->path = $path;

//Insure the the folder is writable by chown apache:apache slim.api/logs and is in share(=writable) path in gluster mode.
//chown apache:apache /path/to/applilogs
$app->lincko->logPath = $app->lincko->path.'/logs';

//Insure the the folder is writable by chown apache:apache slim.api/public and is in share(=writable) path in gluster mode.
//chown apache:apache /path/to/applipublic
$app->lincko->publicPath = $app->lincko->path.'/public';

//False if we want to use Slim error display, use True for json application
$app->lincko->jsonException = true;

$app->lincko->enableSession = false;

//Use true for development to show error message on browser screen
//Do not allow that for production, in case of any single bug, all users will see the message
$app->lincko->showError = false;

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

//Standard value for credential operation only
$app->lincko->security = array(
	'public_key' => 'Bruno Martin', //Value for Sign out
	'private_key' => 'Zhang Xiaorui', //Value for Sign out
	'expired' => '7200', //Expiration time in seconds (2H)
);

//Class with email default parameters, it use local Sendmail.postfix function
$app->lincko->email = new \stdClass;
$app->lincko->email->CharSet = 'utf-8';
$app->lincko->email->Abuse = 'abuse@'.$app->lincko->domain;
$app->lincko->email->Sender = 'noreply@'.$app->lincko->domain;
$app->lincko->email->From = 'noreply@'.$app->lincko->domain;
$app->lincko->email->FromName = $app->lincko->title.' team';
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
	'workspace' => '',
);

////////////////////////////////////
// BUNDLE lincko/api
////////////////////////////////////

$app->lincko->data['workspace_id'] = 0; //Share workspace by default
$app->lincko->data['create_user'] = false; //True if we want to be able to authorize the user creation (this is set to true by the framework)
$app->lincko->data['allow_create_user'] = false; //True if we want to be able to authorize the user creation (this is set to true by the developper, upper level)
$app->lincko->data['invitation_beta'] = true; //At true, we force new user to be invited by someone, at false anyone get register
$app->lincko->data['invitation_code'] = ''; //The code grab by the link

$app->lincko->data['lastvisit'] = time()-1; //Less one second to avoid missing timestamp at the same time

//It will set to true some fields if the API key has access to some part of the application
$app->lincko->api = array();

//List of route names accepted without sigining in
$app->lincko->routeFilter = array(
	'user_signin_post',
	'user_create_post',
	'user_signout_get',
	'user_signout_post',
);

//The hook ModifyRequest will redirect to the right class method according to the method requested in the body, it's not HTTP request which is always POST expect for file uploading (GET to get form, or POST to upload file)
$app->lincko->method_suffix = '_invalid';
//Input stream (readable one time only; not available for multipart/form-data requests)
$contents = @file_get_contents('php://input');
if($contents && isset(json_decode($contents)->method) && $method = mb_strtolower(json_decode($contents)->method)){
	$app->lincko->method_suffix = '_'.$method;
} else {
	$app->lincko->method_suffix = '_'.strtolower($app->request->getMethod());
}

//Fillin the information about public and private key to the client side
$app->lincko->securityFlash = array();
