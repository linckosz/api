<?php
// Category 15

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Datassl;
use \libs\STR;
use \libs\Email;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\data\Workspaces;

/*

USERS

	user/read => post
		+id [integer] (the ID of the element)

	user/create => post
		+email [string | login]
		+password [string]
		-username [string] (name mainly displayed in UI. Might be used for login too, so it's unique)
		-firstname [string]
		-lastname [string]
		-gender [boolean]

	user/update => post
		+id [integer]
		-username [string]
		-firstname [string]
		-lastname [string]
		-gender [boolean]

	user/delete => post
	!rejected!

	user/restore => post
	!rejected!

*/

class ControllerUser extends Controller {

	protected $app = NULL;
	protected $data = NULL;
	protected $form = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		if(isset($this->data->data) && isset($this->data->data->email) && isset($this->data->data->password)){
			$this->data->data->password = Datassl::decrypt($this->data->data->password, $this->data->data->email);
		}
		$this->form = new \stdClass;
		$this->setFields();
		return true;
	}

	protected function setFields(){
		$app = $this->app;
		$form = new \stdClass;
		if(!isset($this->data->data)){
			$app->render(400, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 0, 4)), 'error' => true,)); //No data form received.
			return true;
		} else {
			$form = $this->data->data;
		}
		//Convert NULL to empty string to help isset returning true
		foreach ($form as $key => $value) {
			if(!is_numeric($value) && empty($value)){ //Exclude 0 to become an empty string
				$form->$key = '';
			}
		}
		if(isset($form->id) && is_numeric($form->id)){
			$form->id = (int) $form->id;
		}
		if(isset($form->temp_id) && is_string($form->temp_id)){
			$form->temp_id = trim($form->temp_id);
		}
		if(isset($form->email) && is_string($form->email)){
			$form->email = trim(STR::break_line_conv($form->email,' '));
		}
		if(isset($form->username) && is_string($form->username)){
			$form->username = trim(STR::break_line_conv($form->username,' '));
		}
		if(isset($form->firstname) && is_string($form->firstname)){
			$form->firstname = trim(STR::break_line_conv($form->firstname,' '));
		}
		if(isset($form->lastname) && is_string($form->lastname)){
			$form->lastname = trim(STR::break_line_conv($form->lastname,' '));
		}
		if(isset($form->gender)){
			$form->gender = (int) boolval($form->gender);
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 15, 1)."\n"; //Account creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!$app->lincko->data['create_user']){
			$errmsg = $app->trans->getBRUT('api', 15, 19); //You need to sign out from the application before being able to create a new user account.
		}
		else if(!isset($form->email) || !Users::validEmail($form->email)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
		}
		else if(!isset($form->password) || !Users::validPassword($form->password)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 12); //We could not validate the password format: - Between 6 and 60 characters - Alphanumeric
			$errfield = 'password';
		}
		else if(isset($form->username) && !Users::validChar($form->username, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 10); //We could not validate the username format: - 104 characters max - Without space
			$errfield = 'username';
		}
		else if(isset($form->firstname) && !Users::validChar($form->firstname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 8); //We could not validate the first name format: - 104 characters max
			$errfield = 'firstname';
		}
		else if(isset($form->lastname) && !Users::validChar($form->lastname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 9); //We could not validate the last name format: - 104 characters max
			$errfield = 'lastname';
		}
		else if(isset($form->gender) && !Users::validBoolean($form->gender, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 21); //We could not validate the gender.
			$errfield = 'gender';
		}
		else if($model = new Users()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->email = $form->email;
			$model->password = $form->password;
			if(isset($form->username)){ $model->username = $form->username; } //Optional
			if(isset($form->firstname)){ $model->firstname = $form->firstname; } //Optional
			if(isset($form->lastname)){ $model->lastname = $form->lastname; } //Optional
			if(isset($form->gender)){ $model->gender = $form->gender; } //Optional

			$app->flashNow('signout', true);
			$limit = 1;
			$email = mb_strtolower($model->email);
			if(isset($model->username)){
				$username = $model->username;
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
				if(isset($model->username)){
					$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 18); //Username already in use.
					$errfield = 'username';
				}
			} else if(Users::where('email', '=', $email)->first()){
				$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 10); //Email address already in use.
				$errfield = 'email';
			} else if($limit<1000){
				$app->lincko->data['user_log'] = new UsersLog();
				$app->lincko->data['user_log']->username_sha1 = $username_sha1;
				$app->lincko->data['user_log']->password = password_hash($model->password, PASSWORD_BCRYPT);
				$model->username = $username;
				$model->username_sha1 = $username_sha1;
				$model->internal_email = $internal_email;
				$model->pivots_format($form, false);
				if($model->save()){
					$app->flashNow('signout', false);
					$app->flashNow('resignin', false);
					$app->flashNow('username', $model->username);
					//Setup public and private key
					if($authorize = $app->lincko->data['user_log']->authorize($this->data)){
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
					$mail->msgHTML('<html><body>Hello,<br /><br />Congratulations, you\'ve created an account on '.$app->lincko->title.' website!<br /><br />Best regards,<br /><br />'.$app->lincko->title.' team</body></html>');
					$mail->sendLater();

					$app->render(201, array('msg' => array('msg' => $app->trans->getBRUT('api', 15, 2)."\n".$app->trans->getBRUT('api', 15, 11)),)); //Account created. Check your email for validation code.
					return true;
				}
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form),'Account creation failed',__FILE__,true);
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function read_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 15, 3)."\n"; //Account access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Users::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		}
		else if($model = Users::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 15, 4); //Account accessed.
				$data = new Data();
				$force_partial = new \stdClass;
				$force_partial->$uid = new \stdClass;
				$force_partial->$uid->$key = new \stdClass;
				$force_partial->$uid->$key->{$form->id} = new \stdClass;
				$partial = $data->getMissing($force_partial);
				if(isset($partial) && isset($partial->$uid) && !empty($partial->$uid)){
					$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial, 'info' => 'reading')));
					return true;
				}
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function update_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 15, 5)."\n"; //Account update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Users::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 27); //We could not validate the user account ID.
			$errfield = 'id';
		}
		//"email" and "password" are treated differently for security reason
		else if(isset($form->username) && !Users::validChar($form->username, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 10); //We could not validate the username format: - 104 characters max - Without space
			$errfield = 'username';
		}
		else if(isset($form->firstname) && !Users::validChar($form->firstname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 8); //We could not validate the first name format: - 104 characters max
			$errfield = 'firstname';
		}
		else if(isset($form->lastname) && !Users::validChar($form->lastname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 9); //We could not validate the last name format: - 104 characters max
			$errfield = 'lastname';
		}
		else if(isset($form->gender) && !Users::validBoolean($form->gender, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 21); //We could not validate the gender.
			$errfield = 'gender';
		}
		else if($model = Users::find($form->id)){
			if(isset($form->username)){ $model->username = $form->username; } //Optional
			if(isset($form->firstname)){ $model->firstname = $form->firstname; } //Optional
			if(isset($form->lastname)){ $model->lastname = $form->lastname; } //Optional
			if(isset($form->gender)){ $model->gender = $form->gender; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if(isset($form->username) && Users::where('username', '=', $form->username)->first()){
					$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 18); //Username already in use.
					$errfield = 'username';
				} else if($model->save()){
					$msg = array('msg' => $app->trans->getBRUT('api', 15, 6)); //Account updated.
					$data = new Data();
					$data->dataUpdateConfirmation($msg, 200);
					return true;
				}
			} else {
				$errmsg = $app->trans->getBRUT('api', 8, 29); //Already up to date.
				$app->render(200, array('show' => false, 'msg' => array('msg' => $errmsg)));
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function delete_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 15, 7)."\n".$app->trans->getBRUT('api', 0, 6); //Account deletion failed. You are not allowed to delete the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 15, 20)."\n".$app->trans->getBRUT('api', 0, 9); //Account restoration failed. You are not allowed to restore the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function signin_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$app->render(400, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 0, 4)), 'error' => true,)); //No data form received.
			return false;
		}
		$form = $data->data;

		$errmsg = $app->trans->getBRUT('api', 15, 12)."\n".$app->trans->getBRUT('api', 0, 7); //Sign in failed. Please try again.
		$errfield = 'undefined';

		if(Users::isValid($form)){
			if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
				if($user_log = UsersLog::where('username_sha1', '=', $user->username_sha1)->first()){
					if($authorize = $user_log->authorize($data)){
						$msg = $app->trans->getBRUT('api', 15, 13); //You are already signed in.
						if(isset($authorize['private_key'])){
							$app->flashNow('private_key', $authorize['private_key']);
							$msg = $app->trans->getBRUT('api', 15, 14); //Your session has been extended.
						}
						if(isset($authorize['public_key'])){
							$app->flashNow('public_key', $authorize['public_key']);
							$app->lincko->translation['user_username'] = ucfirst($user->username);
							$msg = $app->trans->getBRUT('api', 15, 15); //Hello @@user_username~~, you are signed in to your account.
						}
						if(isset($authorize['username_sha1'])){
							$app->flashNow('username_sha1', $authorize['username_sha1']);
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
		$app->render(201, array('msg' => array('msg' => $app->trans->getBRUT('api', 15, 14)),)); //Your session has been extended.
		return true;
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

		$msg = $app->trans->getBRUT('api', 15, 16); //You are already signed out.

		if($var = Authorization::find_finger($data->public_key, $data->fingerprint)){
			$var = $var->delete();
			$msg = $app->trans->getBRUT('api', 15, 17); //You have signed out of your account.
		}

		$app->flashNow('signout', true);
		$app->render(200, array('msg' => $msg,));
		return true;
	}

}
