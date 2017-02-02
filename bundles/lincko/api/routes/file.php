<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/file', function () use ($app) {

	$app->map(
		'/create',
		'\bundles\lincko\api\controllers\ControllerFile:create'.$app->lincko->method_suffix
	)
	->via('POST', 'OPTIONS')
	->name('file_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerFile:read'.$app->lincko->method_suffix
	)
	->name('file_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerFile:update'.$app->lincko->method_suffix
	)
	->name('file_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerFile:delete'.$app->lincko->method_suffix
	)
	->name('file_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerFile:restore'.$app->lincko->method_suffix
	)
	->name('file_restore'.$app->lincko->method_suffix);

	$app->map(
		'/result',
		'\bundles\lincko\api\controllers\ControllerFile:result'
	)
	->via('GET', 'POST', 'OPTIONS', 'HEAD')
	->name('file_result'.$app->lincko->method_suffix);

	$app->get(
		'/:workspace/:sha/:type/:id/:name',
		'\bundles\lincko\api\controllers\ControllerFile:open_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'sha' => '[=\d\w]+',
		'type' => 'link|thumbnail|download',
		'id' => '\d+',
		'name' => '.+',
	))
	->name('file_open_get');

	$app->get(
		'/:workspace/:sha/qrcode/:id/:name',
		'\bundles\lincko\api\controllers\ControllerFile:qrcode_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'sha' => '[\d\w]+',
		'id' => '\d+',
		'name' => '.+',
	))
	->name('file_qrcode_get');

	$app->post(
		'/progress/:id',
		'\bundles\lincko\api\controllers\ControllerFile:progress_post'
	)
	->conditions(array(
		'id' => '\d+',
	))
	->name('file_progress_post');

	$app->get(
		'/profile/:workspace/:uid',
		'\bundles\lincko\api\controllers\ControllerFile:profile_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'uid' => '\d+',
	))
	->name('file_profile_get');

	$app->get(
		'/link_from_qrcode/:workspace/:sha(/:mini)',
		'\bundles\lincko\api\controllers\ControllerFile:link_from_qrcode_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'sha' => '[\d\w]+',
		'mini' => '[\w\d]+',
	))
	->name('file_link_from_qrcode_get');

	$app->get(
		'/onboarding/:workspace/:id.mp4',
		'\bundles\lincko\api\controllers\ControllerFile:onboarding_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'id' => '\d+',
	))
	->name('file_onboarding_get');

});

//Third party connection (more secured)
$app->group('/files', function () use ($app) {

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerFile:read'.$app->lincko->method_suffix
	)
	->name('file_read'.$app->lincko->method_suffix);

	$app->post(
		'/upload',
		'\bundles\lincko\api\controllers\ControllerFile:upload'.$app->lincko->method_suffix
	)
	->name('files_upload'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerFile:update'.$app->lincko->method_suffix
	)
	->name('files_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerFile:delete'.$app->lincko->method_suffix
	)
	->name('file_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerFile:restore'.$app->lincko->method_suffix
	)
	->name('files_restore'.$app->lincko->method_suffix);

	$app->post(
		'/progress',
		'\bundles\lincko\api\controllers\ControllerFile:progress_post'
	)
	->name('files_progress_post');

	$app->post(
		'/:type/:sha/:id/:name',
		'\bundles\lincko\api\controllers\ControllerFile:open_post'
	)
	->conditions(array(
		'type' => 'link|thumbnail|download',
		'sha' => '[=\d\w]+',
		'id' => '\d+',
		'name' => '.+',
	))
	->name('files_open_post');

});
