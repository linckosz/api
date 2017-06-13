<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

/*

Get the list of files to check if any inconsistancy
	https://api.lincko.com:10443/debug/md5
	https://bruno.api.lincko.cafe:10443/debug/md5

Return all users actions as strings
	https://api.lincko.com:10443/info/action/3
	https://bruno.api.lincko.cafe:10443/info/action/6

Return all users actions as strings
	https://api.lincko.com:10443/info/list_users/2017-02-17/2017-02-19/
	https://bruno.api.lincko.cafe:10443/info/list_users/2017-02-01/2017-02-18/

Return week-scale reporting
	https://api.lincko.com:10443/info/weeks
	https://bruno.api.lincko.cafe:10443/info/weeks

Return individual sales perfomances
	https://api.lincko.com:10443/info/sales/3qOflwtr3dHXPc06T8m4sg==
	https://bruno.api.lincko.cafe:10443/info/sales/3qOflwtr3dHXPc06T8m4sg==

*/

$app->group('/info', function () use ($app) {

	$app->post(
		'/beginning',
		'\bundles\lincko\api\controllers\ControllerInfo:beginning'.$app->lincko->method_suffix
	)
	->name('info_beginning'.$app->lincko->method_suffix);

	$app->post(
		'/action',
		'\bundles\lincko\api\controllers\ControllerInfo:action_post'
	)
	->name('info_action_post');

	$app->get(
		'/action/:id',
		'\bundles\lincko\api\controllers\ControllerInfo:action_get'
	)
	->conditions(array(
		'id' => '\d+',
	))
	->name('info_action_get');

	$app->get(
		'/list_users/:from/:to/',
		'\bundles\lincko\api\controllers\ControllerInfo:list_users_get'
	)
	->conditions(array(
		'from' => '\d{4}-\d{2}-\d{2}',
		'to' => '\d{4}-\d{2}-\d{2}',
	))
	->name('info_list_users_get');

	$app->get(
		'/weeks',
		'\bundles\lincko\api\controllers\ControllerInfo:weeks_get'
	)
	->name('info_weeks_get');

	$app->get(
		'/msg',
		'\bundles\lincko\api\controllers\ControllerInfo:msg_get'
	)
	->name('info_msg_get');

	$app->get(
		'/representative/:sales_id',
		'\bundles\lincko\api\controllers\ControllerInfo:representative_get'
	)
	->conditions(array(
		'sales_id' => '.+',
	))
	->name('info_representative_get');

});

$app->group('/debug', function () use ($app) {
	
	if($app->getMode()==='development'){
		$app->get('/md5', function () use($app) {
			include($app->lincko->path.'/error/md5.php');
		});
	}

});

