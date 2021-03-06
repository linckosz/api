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
$app->lincko->time_record = false; //Turn at true to track and \time_checkpoint('ok');
$app->lincko->time_start = 0;

//Application title
$app->lincko->title = 'Lincko';

//at True it forces a HTTP code response to 200, mainly used to received feedbacks for file posting
$app->lincko->http_code_ok = false;

//Domain name
if(isset($_SERVER['SERVER_HOST'])){
	$app->lincko->domain = $_SERVER['SERVER_HOST'];
} else if(strpos($_SERVER['HTTP_HOST'], ':')){
	$app->lincko->domain = strstr($_SERVER['HTTP_HOST'], ':', true);
} else {
	$app->lincko->domain = $_SERVER['HTTP_HOST'];
}

$app->lincko->domain_restriction = "/^(?:.{1,3}|(?:api|cloud|cron|dc|file|files|info|lincko|mail|mx|ns|pop|smtp|tp|debug|www)\d*)$/ui";

$app->lincko->cookies_lifetime = time()+(3600*24*90); //Valid 3 months

//Do not enable debug when we are using json ajax respond
$app->config(array(
	'debug' => false,
	'mode' => 'production',
	'cookies.encrypt' => true, //Must use $app->getCookie('foo', false);
	'cookies.secret_key' => 'au6G7dbSh87Ws',
	'cookies.lifetime' => $app->lincko->cookies_lifetime,
	'cookies.secure' => false, //At true it keeps record only on SSL connection
	'cookies.path' => '/',
	'cookies.httponly' => true,
	'templates.path' => '..',
	'debug' => false,
));

//Set the cookie via Slim with root domain only work in HTTPS mode
if(isset($_SERVER['HTTPS'])){
	$app->config(array(
		'cookies.domain' => $app->lincko->domain, //get .lincko.cafe
	));
}

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
$app->lincko->session = array(); //Used to edit and keep some session variable value before session_start command

//Each device has a fingerprint
$app->lincko->fingerprint = 'nofingerprint';

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
$app->lincko->email->Port = 587;
$app->lincko->email->Host = 'service1';
$app->lincko->email->List = array();
$app->lincko->email->Support = 'brunoocto@gmail.com'; //For an unknown reason I cannot send email from lincko.com to lincko.com from PHP, but it works from a client

//Translator parameters
//microsoft@lincko.com/ lin**2**5**@#
$app->lincko->translator = array(
	'text_key1' => '8b5032784084462c97cfe442cf489577',
);

//Translation list
$app->lincko->translation = array(
	'domain' => $app->lincko->domain,
	'title' => $app->lincko->title,
);

//Some generic data for twig
$app->lincko->data = array(
	'domain' => $app->lincko->domain,
	'subdomain' => '',
	'lincko_back' => '',
	'title' => $app->lincko->title,
	'workspace' => '',
);

//Messages to be sent along with rendering
$app->lincko->flash = array();


//Integration data
$app->lincko->integration = new \stdClass;
$app->lincko->integration->wechat = array(
	'dev_appid' => '',
	'dev_secretapp' => '',
	'public_appid' => '',
	'public_secretapp' => '',
);
if($app->lincko->domain=='lincko.cafe'){
	$app->lincko->integration->wechat['public_appid'] = 'wx1d84f13b1addb1ba'; //Sandbox (evan)
	$app->lincko->integration->wechat['public_secretapp'] = 'c35d9afab164b528d927db8cb0c394a1'; //Sandbox (evan)
	$app->lincko->integration->wechat['dev_appid'] = 'aaaaaaaaaaaaaaaaaaaaa'; //Not available
	$app->lincko->integration->wechat['dev_secretapp'] = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';//Not available 
} else if($app->lincko->domain=='lincko.co'){
	$app->lincko->integration->wechat['public_appid'] = 'wxb315b38a8267ad72'; //Sandbox (bruno)
	$app->lincko->integration->wechat['public_secretapp'] = 'e0a658f9d2b907ddb4bd61c3827542da'; //Sandbox (bruno)
	$app->lincko->integration->wechat['dev_appid'] = 'wxafd8adb6683d8914'; //Official
	$app->lincko->integration->wechat['dev_secretapp'] = '1fd24f7296c069dccb3aedc9914e2b9e'; //Official
} else if($app->lincko->domain=='lincko.com'){
	$app->lincko->integration->wechat['public_appid'] = 'wx268709cdc1a8e280'; //Official
	$app->lincko->integration->wechat['public_secretapp'] = '03fab389a36166cd1f75a2c94f5257a0'; //Official
	$app->lincko->integration->wechat['dev_appid'] = 'wx8f20e5f247408c94'; //Official
	$app->lincko->integration->wechat['dev_secretapp'] = 'c088e2b2e3c690c6570f875ce0505d19'; //Official
}


if(isset($_SERVER['LINCKO_BACK']) && !empty($_SERVER['LINCKO_BACK'])){
	$app->lincko->data['lincko_back'] = $_SERVER['LINCKO_BACK'].'.';
	if($app->lincko->domain!='lincko.com' && $app->lincko->domain!='lincko.co'){
		$app->lincko->data['subdomain'] = $app->lincko->data['lincko_back'];
	}
}

////////////////////////////////////
// BUNDLE lincko/api
////////////////////////////////////

$app->lincko->data['workspace_id'] = 0; //Share workspace by default
$app->lincko->data['workspace_default_role'] = 2; //Give manager role by default to all users in shared workspace
$app->lincko->data['create_user'] = false; //True if we want to be able to authorize the user creation (this is set to true by the framework)
$app->lincko->data['allow_create_user'] = true; //True if we want to be able to authorize the user creation (this is set to true by the developper, upper level)
$app->lincko->data['need_invitation'] = false; //At true, we force new user to be invited by someone, at false anyone get register
$app->lincko->data['invitation_code'] = ''; //The code grab by the link
$app->lincko->data['party'] = false; //The code grab by the link

$app->lincko->data['lastvisit'] = time()-1; //Less one second to avoid missing timestamp at the same time
$app->lincko->data['lastvisit_enabled'] = true;

$app->lincko->data['remote'] = false; //At true if we connect to remote server
$app->lincko->data['database_data'] = 'data'; //data is the local, but it can be changed to third party database for more security

$app->lincko->workspace = array(
	'public' => true, //[true] The workspace is switchable from anywhere
	'open_access' => false, //[8 alphanumeric] Must be the entry point as a get parameter ?open_access=1R47s82a , without it the user cannot log in
);

$app->lincko->filePathPrefix = '';

//It will set to true some fields if the API key has access to some part of the application
$app->lincko->api = array();

//List of route names accepted without sigining in (mainly used for credential operation)
$app->lincko->routeFilter = array(
	'user_signin_post',
	'user_create_post',
	'user_signout_get',
	'user_signout_post',
	'user_forgot_post',
	'user_reset_post',
	'email_verify_post',
	'email_exists_post',
);

//List of route names accepted without sigining in and do the call immediatly (faster)
$app->lincko->routeSkip = array(
	'integration_code_get',
	'integration_setcode_get',
	'integration_set_wechat_qrcode_post',
	'integration_get_wechat_token_post',
	'invitation_email_post',
	'debug_md5_get',
	'debug_get',
);

//The hook ModifyRequest will redirect to the right class method according to the method requested in the body, it's not HTTP request which is always POST expect for file uploading (GET to get form, or POST to upload file)
$app->lincko->method_suffix = '_invalid';
//Input stream (readable one time only; not available for multipart/form-data requests)
$contents = @file_get_contents('php://input');
if($contents){
	$contents = json_decode($contents);
	if(isset($contents->method) && $method = mb_strtolower($contents->method)){
		$app->lincko->method_suffix = '_'.$method;
	}
} else {
	$app->lincko->method_suffix = '_'.strtolower($app->request->getMethod());
}
