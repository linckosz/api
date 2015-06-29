<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Datassl;
use \libs\STR;
use \libs\Email;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;

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
			$app->render(400, array('msg' => array('msg' => $app->trans->getBRUT('api', 0, 4), 'field' => 'undefined'), 'error' => true,)); //No data form received.
			return false;
		}
		$form = $data->data;

		$errmsg = $app->trans->getBRUT('api', 1, 0); //Sign in failed. Please try again.
		$errfield = 'undefined';

		if(UsersLog::isValid($form)){
			if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
				if($user_log = UsersLog::where('username_sha1', '=', $user->username_sha1)->first()){
					if($authorize = $user_log->authorize($data)){
						$msg = $app->trans->getBRUT('api', 1, 1); //You are already signed in.
						if(isset($authorize['private_key'])){
							$app->flashNow('private_key', $authorize['private_key']);
							$msg = $app->trans->getBRUT('api', 1, 2); //Your session has been extended.
						}
						if(isset($authorize['public_key'])){
							$app->flashNow('public_key', $authorize['public_key']);
							$app->lincko->translation['user_username'] = ucfirst($user->username);
							$msg = $app->trans->getBRUT('api', 1, 3); //Hello @@user_username~~, you are signed in to your account.
						}
						if(isset($authorize['username_sha1'])){
							$app->flashNow('username_sha1', $authorize['username_sha1']);
						}
						if(isset($authorize['uid'])){
							$app->flashNow('uid', $authorize['uid']);
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
							'msg' => array('msg' => $msg, 'field' => 'undefined'),
						));
						return true;
					}
				}
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form),'Sign in failed',__FILE__,true);
		$app->flashNow('signout', true);
		$app->render(401, array('msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true,));
		return false;
	}

	public function resign_get(){
		$app = $this->app;
		//Do nothing, the middleware CheckAccess will handle automatically the resigning action
		$app->render(201, array('msg' => array('msg' => $app->trans->getBRUT('api', 1, 2), 'field' => 'undefined'),)); //Your session has been extended.
		return true;
	}

	public function create_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$app->render(400, array('msg' => array('msg' => $app->trans->getBRUT('api', 0, 4), 'field' => 'undefined'), 'error' => true,)); //No data form received.
			return true;
		}
		$form = $data->data;

		//Clean the fields from useless spaces
		foreach($form as $key => $value) {
			$form->$key = trim($form->$key);
		}

		$errmsg = $app->trans->getBRUT('api', 1, 4); //Account creation failed. Please try again.
		$errfield = 'undefined';
		$app->flashNow('signout', true);

		if(isset($form->firstname) && !Users::validFirstname($form->firstname)){
			$errmsg = $app->trans->getBRUT('api', 1, 13); //Account creation failed. We could not valid the first name format: - 104 characters max
			$errfield = 'firstname';
		} else if(isset($form->lastname) && !Users::validLastname($form->lastname)){
			$errmsg = $app->trans->getBRUT('api', 1, 14); //Account creation failed. We could not valid the last name format: - 104 characters max
			$errfield = 'lastname';
		} else if(isset($form->username) && !Users::validUsername($form->username)){
			$errmsg = $app->trans->getBRUT('api', 1, 12); //Account creation failed. We could not valid the username format: - 104 characters max - Without space
			$errfield = 'username';
		} else if(isset($form->email) && !Users::validEmail($form->email)){
			$errmsg = $app->trans->getBRUT('api', 1, 10); //Account creation failed. We could not valid the Email address format: - 191 characters max
			$errfield = 'email';
		} else if(isset($form->password) && !UsersLog::validPassword($form->password)){
			$errmsg = $app->trans->getBRUT('api', 1, 11); //Account creation failed. We could not valid the password format: - Between 6 and 60 characters - Alphanumeric
			$errfield = 'password';
		} else if(UsersLog::isValid($form)){

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
				$limit = 1; //Limit while loop to 1000 iterations to avoid infinite loop
				while( $limit <= 1000 && Users::where('username', '=', $username)->orWhere('internal_email', '=', $username)->orWhere('username_sha1', '=', $username_sha1)->first() ){
					$username = $username_base.mt_rand(1, 9999);
					$username_sha1 = sha1(mb_strtolower($username));
					$internal_email = $username;
					$limit++;
				}
			}

			if(Users::where('username', '=', $username)->orWhere('internal_email', '=', $internal_email)->orWhere('username_sha1', '=', $username_sha1)->first()){
				//If the field username is missing, we keep the standard error message.
				if(isset($form->username)){
					$errmsg = $app->trans->getBRUT('api', 1, 5); //Account creation failed. Username already in use.
					$errfield = 'username';
				}
			} else if(Users::where('email', '=', $email)->first()){
				$errmsg = $app->trans->getBRUT('api', 1, 6); //Account creation failed. Email address already in use.
				$errfield = 'email';
			} else if($limit<=100){
				$user_log = new UsersLog;
				$user_log->username_sha1 = $username_sha1;
				$user_log->password = password_hash($form->password, PASSWORD_BCRYPT);
				$user = new Users;
				$user->username = $username;
				$user->username_sha1 = $username_sha1;
				$user->email = $email;
				$user->internal_email = $internal_email;
				if(isset($form->firstname)){ $user->firstname = $form->firstname; }
				if(isset($form->lastname)){ $user->lastname = $form->lastname; }
				if($user_log->save() && $user->save()){
					$app->flashNow('signout', false);
					$app->flashNow('resignin', false);
					$app->flashNow('username', $user->username);

					//Setup public and private key
					if($authorize = $user_log->authorize($data)){
						if(isset($authorize['private_key'])){
							$app->flashNow('private_key', $authorize['private_key']);
						}
						if(isset($authorize['public_key'])){
							$app->flashNow('public_key', $authorize['public_key']);
						}
						if(isset($authorize['username_sha1'])){
							$app->flashNow('username_sha1', $authorize['username_sha1']);
						}
						if(isset($authorize['uid'])){
							$app->flashNow('uid', $authorize['uid']);
						}
					}
					
					//Send congrat email
					$mail = new Email();
					$mail->addAddress($email, $username);
					$mail->setSubject('Congratulation');
					$mail->msgHTML('<html><body>Hello,<br /><br />Congratulations, you\'ve created an account on '.$app->lincko->title.' website!<br />Some other text...<br /><br />Best regards,<br /><br />Arc team</body></html>');
					$mail->sendLater();

					$app->render(201, array('msg' => array('msg' => $app->trans->getBRUT('api', 1, 7), 'field' => 'undefined'),)); //Account created. check your email for validation code.
					return true;
				}
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form),'Account creation failed',__FILE__,true);
		$app->render(401, array('msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
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