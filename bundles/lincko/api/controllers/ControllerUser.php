<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Datassl;
use \libs\STR;
use \bundles\lincko\api\models\Users;
use \bundles\lincko\api\models\Authorization;

class ControllerUser extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		
		if(isset($this->data->data) && isset($this->data->data->email) && isset($this->data->data->password)){
			$this->data->data->password = Datassl::decrypt($this->data->data->password, $this->data->data->email);
		}
		
		return true;
	}

	public function signin_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$msg = $app->trans->getBRUT('api', 0, 4); //No data form received.
			$app->render(400, array('msg' => $msg, 'error' => true,));
			return false;
		}
		$form = $data->data;

		$errmsg = $app->trans->getBRUT('api', 1, 0); //Sign in failed. Please try again.

		if(Users::isValid('user_signin',$form)){
			if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
				if($authorize = $user->authorize($data)){
					$msg = $app->trans->getBRUT('api', 1, 1); //You are already signed in.
					if(isset($authorize['private_key'])){
						$app->flashNow('private_key', $authorize['private_key']);
						$msg = $app->trans->getBRUT('api', 1, 2); //Your session has been extended.
					}
					if(isset($authorize['public_key'])){
						$app->flashNow('public_key', $authorize['public_key']);
						$msg = $app->trans->getBRUT('api', 1, 3); //You are signed in to your account.
					}
					if(isset($authorize['refresh'])){
						if($authorize['refresh']){
							$msg = $app->trans->getBRUT('api', 1, 2); //Your session has been extended.
						} else {
							$msg = $app->trans->getBRUT('api', 1, 3); //You are signed in to your account.
						}

					}
					$app->flashNow('username', $user->username);
					$app->render(200, array(
						'msg' => $msg,
					));
					return true;
				}
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form),'Sign in failed',__FILE__,true);
		$app->flashNow('signout', true);
		$app->render(401, array('msg' => $errmsg, 'error' => true,));
		return false;
	}

	public function create_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$msg = $app->trans->getBRUT('api', 0, 4); //No data form received.
			$app->render(400, array('msg' => $msg, 'error' => true,));
			return true;
		}
		$form = $data->data;

		$errmsg = $app->trans->getBRUT('api', 1, 4); //Account creation failed. Please try again.
		$app->flashNow('signout', true);

		if(isset($form->username) && !Users::validUsername($form->username)){
			$errmsg = $app->trans->getBRUT('api', 1, 12); //Account creation failed. We could not valid the username.
		} else if(isset($form->email) && !Users::validEmail($form->email)){
			$errmsg = $app->trans->getBRUT('api', 1, 10); //Account creation failed. We could not valid the Email address.
		} else if(isset($form->password) && !Users::validPassword($form->password)){
			$errmsg = $app->trans->getBRUT('api', 1, 11); //Account creation failed. We could not valid the password.
		} else if(Users::isValid('user_create',$form)){

			$limit = 1;
			$email = mb_strtolower($form->email);
			if(isset($form->username)){
				$username = $form->username;
				$username_sha1 = sha1(mb_strtolower($username));
				$internal_email = $username;
			} else {
				$username = $username_base = mb_strstr($email,'@',true);
				$username_sha1 = sha1(mb_strtolower($username));
				$internal_email = $username;
				$limit = 1; //Limit while loop to 100 iterations to avoid infinity loop
				while( $limit <= 100 && Users::where('username', '=', $username)->orWhere('internal_email', '=', $username)->orWhere('username_sha1', '=', $username_sha1)->first() ){
					$username = $username_base.mt_rand(1, 9999);
					$username_sha1 = sha1(mb_strtolower($username));
					$internal_email = $username;
					$limit++;
				}
			}

			if(Users::where('username', '=', $username)->orWhere('internal_email', '=', $internal_email)->orWhere('username_sha1', '=', $username_sha1)->first()){
				$errmsg = $app->trans->getBRUT('api', 1, 5); //Account creation failed. Username already in use.
			} else if(Users::where('email', '=', $email)->first()){
				$errmsg = $app->trans->getBRUT('api', 1, 6); //Account creation failed. Email address already in use.
			} else if($limit<=100){
				$user = new Users;
				$user->username = $username;
				$user->username_sha1 = $username_sha1;
				$user->password = password_hash($form->password, PASSWORD_BCRYPT);
				$user->email = $email;
				$user->internal_email = $internal_email;
				if($user->save()){
					$app->flashNow('signout', false);
					$app->flashNow('resignin', true);
					$app->render(201, array('msg' => $app->trans->getBRUT('api', 1, 7),)); //Account create. check your email for validation code.
					return true;
				}
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form),'Account creation failed',__FILE__,true);
		$app->render(401, array('msg' => $errmsg, 'error' => true));
		return false;
	}

	public function signout_get(){
		$this->signout_post();
	}

	public function signout_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$msg = $app->trans->getBRUT('api', 0, 4); //No data form received.
			$app->render(400, array('msg' => $msg, 'error' => true,));
			return true;
		}
		$form = $data->data;

		$msg = $app->trans->getBRUT('api', 1, 8); //You are already signed out.

		if($var = Authorization::find($data->public_key)){
			$var = $var->delete();
			$msg = $app->trans->getBRUT('api', 1, 9); //You have signed out of your account.
		}

		$app->flashNow('signout', true);
		$app->render(200, array('msg' => $msg,));
		return true;
	}

}