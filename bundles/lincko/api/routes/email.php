<?php

namespace bundles\lincko\api\routes;

use \bundles\lincko\api\models\UsersLog;

$app = \Slim\Slim::getInstance();

$app->group('/email', function () use ($app) {
	
	$app->post('/verify', function () use($app) {

		$data = json_decode($app->request->getBody());

		if(isset($data->data) && isset($data->data->email)){
			$ve = new \hbattat\VerifyEmail($data->data->email, 'noreply@'.$app->lincko->domain);
			if($ve->verify()){
				$app->render(200, array('show' => false, 'msg' => 'verify', 'error' => false));
				return exit(0);
			}
		}

		$app->render(400, array('show' => false, 'msg' => 'verify', 'error' => true));
		return exit(0);

	})
	->name('email_verify_post');

	$app->post('/exists', function () use($app) {

		$data = json_decode($app->request->getBody());

		if(isset($data->data) && isset($data->data->email)){
			$users_log = UsersLog::Where('party', null)->where('party_id', trim(mb_strtolower($data->data->email)))->first();
			if($users_log){
				$app->render(200, array('show' => false, 'msg' => array('msg' => 'Link', 'exists' => true), 'error' => false));
				return exit(0);
			}
		}

		$app->render(200, array('show' => false, 'msg' => array('msg' => 'Create', 'exists' => false), 'error' => false));
		return exit(0);

	})
	->name('email_exists_post');

});
