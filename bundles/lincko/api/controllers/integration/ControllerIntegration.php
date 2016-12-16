<?php

namespace bundles\lincko\api\controllers\integration;

use \libs\Controller;
use \bundles\lincko\api\models\Integration;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;

class ControllerIntegration extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function connect_post(){
		$app = $this->app;
		$data = $this->data;
		$errmsg = $app->trans->getBRUT('api', 15, 12)."\n".$app->trans->getBRUT('api', 0, 7); //Sign in failed. Please try again.
		$errfield = 'undefined';

		$integration = Integration::getIntegration();
		$flash = Integration::getFlash();

		if($integration && $flash && isset($integration->username_sha1) && isset($flash->public_key) && isset($flash->uid)){
			if($user = Users::Where('username_sha1', '=', $integration->username_sha1)->first()){
				if($flash->uid==$user->id && Authorization::find_finger($flash->public_key, $data->fingerprint)){
					$authorize = (array)$flash;
					$msg = $app->trans->getBRUT('api', 15, 13); //You are already signed in.
					if(isset($authorize['private_key'])){
						$app->flashNow('private_key', $authorize['private_key']);
						$msg = $app->trans->getBRUT('api', 15, 14); //Your session has been extended.
					}
					if(isset($authorize['public_key'])){
						$app->flashNow('public_key', $authorize['public_key']);
						$app->lincko->translation['user_username'] = $user->username;
						$msg = $app->trans->getBRUT('api', 15, 15); //Hello @@user_username~~, you are signed in to your account.
					}
					if(isset($authorize['username_sha1'])){
						$app->flashNow('username_sha1', substr($authorize['username_sha1'], 0, 20)); //Truncate to 20 character because phone alias notification limitation
					}
					if(isset($authorize['uid'])){
						$app->flashNow('uid', $authorize['uid']);
					}
					if(isset($authorize['refresh'])){
						if($authorize['refresh']){
							$msg = $app->trans->getBRUT('api', 15, 14); //Your session has been extended.
						} else {
							$msg = $app->trans->getBRUT('api', 15, 15); //Hello @@user_username~~, you are signed in to your account.
						}
					}
					$app->flashNow('username', $user->username);
					$app->render(200, array('msg' => array('msg' => $msg)));
					return true;
				}
			} else {
				$errmsg = $app->trans->getBRUT('api', 15, 12)."\n".$app->trans->getBRUT('api', 15, 32); //Sign in failed. This account does not exist.
			}
		}
		
		\libs\Watch::php($errmsg, 'Sign in failed', __FILE__, __LINE__, true);
		$app->flashNow('signout', true);
		$app->render(401, array('msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true,));
		return false;
	}

}