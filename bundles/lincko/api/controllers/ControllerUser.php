<?php
// Category 15

namespace bundles\lincko\api\controllers;

use Carbon\Carbon;
use \libs\Controller;
use \libs\Datassl;
use \libs\STR;
use \libs\Email;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\Invitation;
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
		if(is_array($form) || is_object($form)){
			foreach ($form as $key => $value) {
				if(!is_numeric($value) && empty($value)){ //Exclude 0 to become an empty string
					$form->$key = '';
				}
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
		if(isset($form->profile_pic) && is_numeric($form->profile_pic)){
			$form->profile_pic = (int) $form->profile_pic;
		}
		if(isset($form->code) && is_numeric($form->code)){
			$form->code = (int) $form->code;
		}
		if(isset($form->timeoffset) && is_numeric($form->timeoffset)){
			$form->timeoffset = (int) $form->timeoffset;
			if($form->timeoffset<0){
				$form->timeoffset = 24 + $form->timeoffset;
			}
			if($form->timeoffset>=24){
				$form->timeoffset = 0;
			}
		}
		if(isset($form->resume) && is_numeric($form->resume)){
			$form->resume = (int) $form->resume;
			if($form->resume<0){
				$form->resume = 24 + $form->resume;
			}
			if($form->resume>=24){
				$form->resume = 0;
			}
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 15, 1)."\n"; //Account creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!$app->lincko->data['allow_create_user']){
			$errmsg = $app->trans->getBRUT('api', 15, 2); //Because of server maintenance, we temporarily do not allow the creation of new user account, please try later.
		}
		else if(isset($form->invitation_beta) && ($form->invitation_beta=='' || Invitation::withTrashed()->where('code', '=', $form->invitation_beta)->first())){ //optional
			$errmsg = $app->trans->getBRUT('api', 15, 27); //You need an invitation link unused to be able to join us.
		}
		else if(!$app->lincko->data['create_user']){
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
		else if(isset($form->username) && (!Users::validChar($form->username, true) || !Users::validTextNotEmpty($form->username, true))){ //Optional
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
			if(isset($form->profile_pic)){ $model->profile_pic = $form->profile_pic; } //Optional
			if(isset($form->timeoffset)){ $model->timeoffset = $form->timeoffset; } //Optional
			if(isset($form->resume)){ $model->resume = $form->resume; } //Optional
			$app->flashNow('signout', true);
			$limit = 1;
			$email = mb_strtolower($model->email);
			if(isset($model->username)){
				$username = $model->username;
			} else {
				$username = mb_strstr($email,'@',true);
			}
			$username_sha1 = sha1(mb_strtolower($email));
			$internal_email = $username;
			$limit = 1; //Limit while loop to 1000 iterations to avoid infinite loop
			while( $limit <= 1000 && Users::where('internal_email', '=', $username)->orWhere('username_sha1', '=', $username_sha1)->first() ){
				$internal_email = $username.mt_rand(1, 9999);
				$username_sha1 = sha1(mb_strtolower($internal_email));
				$limit++;
			}

			$invitation = false;
			$invitation_used = false;
			if(isset($form->invitation_code)){
				$invitation_code = $form->invitation_code;
				if($invitation = Invitation::withTrashed()->where('code', '=', $invitation_code)->first()){
					$invitation_used = $invitation->used;
				}
			}

			/*
			Those lines were used for closed beta
			if(!$invitation){
				$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 29); //Invitation code already used.
			} else if($invitation_used){
				$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 29); //Invitation code already used.
			}
			*/
			if(Users::where('internal_email', '=', $internal_email)->orWhere('username_sha1', '=', $username_sha1)->first()){
				//If the field username is missing, we keep the standard error message.
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
				if($model->getParentAccess() && $model->save()){
					$app->lincko->data['uid'] = $model->id;
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
					$link = 'https://'.$app->lincko->domain;
					$mail = new Email();

					$mail_subject = $app->trans->getBRUT('api', 1003, 1); //Congratulations on joining Lincko!
					$mail_body_array = array(
						'mail_username' => $username,
						'mail_link' => $link,
					);
					$mail_body = $app->trans->getBRUT('api', 1003, 2, $mail_body_array); //Congratulations on joining Lincko. Here’s a link to help you start using Lincko and get on with your journey....

					$mail_template_array = array(
						'mail_head' => $mail_subject,
						'mail_body' => $mail_body,
						'mail_foot' => '',
					);
					$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);

					$mail->addAddress($email, $username);
					$mail->setSubject($mail_subject);
					$mail->sendLater($mail_template);

					//Invitation
					if($invitation){
						$pivot = new \stdClass;
						if($invitation->created_by>0 && Users::find($invitation->created_by)){
							//For guest & host
							$pivot->{'users>access'} = new \stdClass;
							$pivot->{'users>access'}->{$invitation->created_by} = true;
							$model->pivots_format($pivot);
							$model->forceSaving();
							$model->save();
						}
						//Record for invotation
						$invitation->guest = $model->id;
						$invitation->used = true;
						$invitation->save();
					}

					$app->render(201, array('msg' => array('show' => false, 'msg' => $app->trans->getBRUT('api', 15, 2)."\n".$app->trans->getBRUT('api', 15, 11)),)); //Account created. Check your email for validation code.
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
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 15, 5)."\n"; //Account update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Users::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 27); //We could not validate the user account ID.
			$errfield = 'id';
		}
		//"email" and "password" are treated differently for security reason
		else if(isset($form->username) && (!Users::validChar($form->username, true) || !Users::validTextNotEmpty($form->username, true))){ //Optional
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
			if(isset($form->profile_pic)){ $model->profile_pic = $form->profile_pic; } //Optional
			if(isset($form->timeoffset)){ $model->timeoffset = $form->timeoffset; } //Optional
			if(isset($form->resume)){ $model->resume = $form->resume; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$msg = array('msg' => $app->trans->getBRUT('api', 15, 6)); //Account information updated.
					$data = new Data();
					$data->dataUpdateConfirmation($msg, 200, false, $lastvisit);
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
							$app->lincko->translation['user_username'] = $user->username;
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
					} else {
						$errmsg = $app->trans->getBRUT('api', 15, 12)."\n".$app->trans->getBRUT('api', 15, 31); //Sign in failed. Wrong password.
					}
				} else {
					$errmsg = $app->trans->getBRUT('api', 15, 12)."\n".$app->trans->getBRUT('api', 0, 7); //Sign in failed. Please try again.
				}
			} else {
				$errmsg = $app->trans->getBRUT('api', 15, 12)."\n".$app->trans->getBRUT('api', 15, 32); //Sign in failed. This account does not exist.
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
		return $this->resign_post();
	}

	public function resign_post(){
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

	public function find_post(){
		$app = $this->app;

		$failmsg = $app->trans->getBRUT('api', 15, 3)."\n"; //Account access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		$form = $this->form;
		if(is_array($form) || is_object($form)){
			foreach ($form as $key => $value) {
				$email = mb_strtolower($value);
				break; //Insure to take the first one only
			}
			if($user = Users::where('email', $email)->first()){ //Account found
				$data = new \stdClass;
				$data->myself = false;
				if($user->id == $app->lincko->data['uid']){
					$data->myself = true;
				}
				$data->contact = false;
				if(Users::getModel($user->id)){
					$data->contact = true;
				}
				$data->id = $user->id;
				$data->username = $user->username;
				$data->profile_pic = $user->profile_pic;
				$msg = $app->trans->getBRUT('api', 15, 23); //Account found
				$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
				return true;
			} else {
				$data = true;
				$msg = $app->trans->getBRUT('api', 15, 24); //Account not found
				$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function invite_post(){
		$app = $this->app;

		$failmsg = $app->trans->getBRUT('api', 15, 3)."\n"; //Account access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		$form = $this->form;
		if(is_array($form) || is_object($form)){
			$form = (object) $form;
			if(!$form->exists && isset($form->email)){
				if(!Users::where('email', $form->email)->first()){
					$user = Users::getUser();
					$username = $user->username;
					$invitation = new Invitation();
					$invitation->save();
					$code = $invitation->code;
					$link = 'https://'.$app->lincko->domain.'/invitation/'.$code;
					$mail = new Email();

					$mail_subject = $app->trans->getBRUT('api', 1001, 1); //Your invitation to join Lincko
					$mail_body_array = array(
						'mail_username' => $username,
						'mail_link' => $link,
					);
					$mail_body = $app->trans->getBRUT('api', 1001, 2, $mail_body_array); //Hello,@@username~~ has invited you to join Lincko. Lincko helps you accomplish great....
					$mail_foot = $app->trans->getBRUT('api', 1001, 3); //You are receiving this e-mail because someone invited you to collaborate together using Lincko.

					$mail_template_array = array(
						'mail_head' => $mail_subject,
						'mail_body' => $mail_body,
						'mail_foot' => $mail_foot,
					);
					$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);

					$mail->addAddress($form->email);
					$mail->setSubject($mail_subject);
					if($mail->sendLater($mail_template)){
						$data = true;
						$msg = $app->trans->getBRUT('api', 15, 26); //Invitation sent
						$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
						return true;
					}
				}
			} else if($form->exists && isset($form->users_id)){
				if($guest = Users::find($form->users_id)){
					$user = Users::getUser();
					$username = $user->username;
					$username_guest = $guest->username;
					$pivot = new \stdClass;
					$pivot->{'usersLinked>invitation'} = new \stdClass;
					$pivot->{'usersLinked>invitation'}->{$form->users_id} = true;
					$pivot->{'usersLinked>access'} = new \stdClass;
					$pivot->{'usersLinked>access'}->{$form->users_id} = false;
					$user->pivots_format($pivot);
					$user->save();
					$link = 'https://'.$app->lincko->domain;
					$mail = new Email();

					$mail_subject = $app->trans->getBRUT('api', 1002, 1); //New Lincko collaboration request
					$mail_body_array = array(
						'mail_username_guest' => $username_guest,
						'mail_username' => $username,
						'mail_link' => $link,
					);
					$mail_body = $app->trans->getBRUT('api', 1002, 2, $mail_body_array); //You have a new collaboration request!<br><br>@@mail_username~~ has invited you to collaborate together using Lincko.

					$mail_template_array = array(
						'mail_head' => $mail_subject,
						'mail_body' => $mail_body,
						'mail_foot' => '',
					);
					$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);

					$mail->addAddress($guest->email);
					$mail->setSubject($mail_subject);
					if($mail->sendLater($mail_template)){
						$data = true;
						$msg = $app->trans->getBRUT('api', 15, 26); //Invitation sent
						$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
						return true;
					}
				}
				
			}
			$data = false;
			$msg = $app->trans->getBRUT('api', 15, 25); //Invitation failed to send.
			$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
			return true;
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function my_user_get(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 15, 3)."\n"; //Account access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if($model = Users::getUser()){
			if($model->checkAccess(false)){
				$app->render(200, array('msg' => $model->toJson()));
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function forgot_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 15, 24)."\n"; //Account not found
		$errmsg = $failmsg;
		$errfield = 'undefined';

		if(!isset($form->email) || !Users::validEmail($form->email)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
		}
		else if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
			if($user_log = UsersLog::where('username_sha1', '=', $user->username_sha1)->first()){
				$user_log->code = substr(str_shuffle("123456789"), 0, 6);
				$limit = Carbon::now();
				$limit->second = 610; //We give 10 minutes to enter the code (including 10 more seconds to cover communication latency)
				$user_log->code_limit = $limit;
				$user_log->code_try = 3; //We give 3 shots to success
				if($user_log->save()){
					$mail = new Email();
					$mail_subject = $app->trans->getBRUT('api', 1004, 1); //Password reset
					$mail_body_array = array(
						'mail_username' => $user->username,
						'mail_code' => $user_log->code,
					);
					$mail_body = $app->trans->getBRUT('api', 1004, 2, $mail_body_array); //You have resquested a password reset. You need to eneter the code below within 2 minutes in the required field to be able to confirm the operation. CODE: <b>@@mail_code~~<b/>
					$mail_template_array = array(
						'mail_head' => $mail_subject,
						'mail_body' => $mail_body,
						'mail_foot' => '',
					);
					$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);
					$mail->addAddress($user->email);
					$mail->setSubject($mail_subject);
					if($mail->sendLater($mail_template)){
						$msg = $app->trans->getBRUT('api', 15, 33); //You will receive an email with a Code.
						$app->render(200, array('show' => true, 'msg' =>  array('msg' => $msg, 'email' => $user->email), 'error' => false));
						return true;
					}
				}

			}	
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function reset_post(){
		$app = $this->app;
		$form = $this->form;
		$reset = false;

		$failmsg = $app->trans->getBRUT('api', 0, 10)."\n"; //Operation failed
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->email) || !Users::validEmail($form->email)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
		}
		else if(!isset($form->code) || !Users::validCode($form->code)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 31); //Please enter the correct code
			$errfield = 'code';
		}
		else if(!isset($form->password) || !Users::validPassword($form->password)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 12); //We could not validate the password format: - Between 6 and 60 characters - Alphanumeric
			$errfield = 'password';
		}
		else if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
			if($user_log = UsersLog::where('username_sha1', '=', $user->username_sha1)->Where('code', '!=', null)->first()){
				if($user_log->code == $form->code){
					$now = time();
					$code_limit = (new \DateTime($user_log->code_limit))->getTimestamp();
					if($code_limit >= $now){
						$user_log->old_password = $user_log->password; //Just in case, keep the old password in memory
						$user_log->password = password_hash($form->password, PASSWORD_BCRYPT);
						//Hide the password to avoid hacking
						if(isset($form->password)){
							$form->password = '******';
						}
						$user_log->code = null;
						$user_log->code_limit = null;
						$user_log->code_try = 0;
						if($user_log->save()){
							$mail = new Email();
							$mail_subject = $app->trans->getBRUT('api', 1005, 1); //Password reset - Confirmation
							$link = 'https://'.$app->lincko->domain;
							$mail_body_array = array(
								'mail_username' => $user->username,
								'mail_link' => $link,
							);
							$mail_body = $app->trans->getBRUT('api', 1005, 2, $mail_body_array); //You have successfully reset your password. You can now signin.
							$mail_template_array = array(
								'mail_head' => $mail_subject,
								'mail_body' => $mail_body,
								'mail_foot' => '',
							);
							$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);
							$mail->addAddress($user->email);
							$mail->setSubject($mail_subject);
							if($mail->sendLater($mail_template)){
								$msg = $app->trans->getBRUT('api', 15, 34); //Password successfully reset
								$app->render(200, array('show' => true, 'msg' =>  array('msg' => $msg), 'error' => false));
								return true;
							}
						}
					} else {
						$user_log->code = null;
						$user_log->code_limit = null;
						$user_log->code_try = 0;
						$reset = true;
						$user_log->save();
					}
				} else {
					$code_try = (int)$user_log->code_try;
					$code_try--;
					if($code_try<=0){
						$code_try = 0;
						$reset = true;
						$user_log->code = null;
						$user_log->code_limit = null;
					}
					$user_log->code_try = $code_try;
					$user_log->save();
				}
			}	
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}

		if($reset){
			$errmsg = $app->trans->getBRUT('api', 0, 10); //Operation failed
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield, 'reset' => $reset), 'error' => true));
		return false;
	}

}
