<?php
// Category 15

namespace bundles\lincko\api\controllers;

use Carbon\Carbon;
use \libs\Controller;
use \libs\Datassl;
use \libs\STR;
use \libs\Json;
use \libs\Email;
use \libs\Translation;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\Onboarding;
use \bundles\lincko\api\models\Integration;
use \bundles\lincko\api\models\Inform;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\Invitation;
use Illuminate\Database\Capsule\Manager as Capsule;

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
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
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
		//Convert to object
		$form = (object)$form;
		//Convert NULL to empty string to help isset returning true
		if(is_array($form) || is_object($form)){
			foreach ($form as $key => $value) {
				if(!is_numeric($value) && empty($value)){ //Exclude 0 to become an empty string
					$form->$key = '';
				}
			}
		}

		//If default account and email field is fill in, we consider email (original format) as party_id
		if(!isset($form->party) || empty($form->party)){
			$form->party = null; //Insure to define party
			if((!isset($form->party_id) || empty($form->party_id)) && isset($form->email) && !empty($form->email)){
				$form->party_id = $form->email;
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
		if(isset($form->invite_access)){
			if(!empty($form->invite_access) && (gettype($form->invite_access)=='object' || gettype($form->invite_access)=='array')){
				$form->invite_access = json_encode((object) $form->invite_access);
			} else {
				unset($form->invite_access);
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
		else if($app->lincko->data['need_invitation'] && isset($form->invitation_code) && ($form->invitation_code=='' || Invitation::withTrashed()->where('code', $form->invitation_code)->first())){ //optional
			$errmsg = $app->trans->getBRUT('api', 15, 27); //You need an invitation link unused to be able to join us.
		}
		else if(!$app->lincko->data['create_user']){
			$errmsg = $app->trans->getBRUT('api', 15, 19); //You need to sign out from the application before being able to create a new user account.
		}
		//By direct operation, we only allow email account creation, not any integration
		else if(isset($form->party) && !empty($form->party)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 34); //We could not validate the format
			$errfield = 'party';
		}
		else if(!isset($form->party_id) || empty($form->party_id) || !Users::validChar($form->party_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 34); //We could not validate the format
			$errfield = 'party_id';
		}
		//party must be defined in setForm and is null for default Lincko account login system
		else if(empty($form->party) && (!isset($form->party_id) || !isset($form->password) || !Users::validPassword(Datassl::decrypt($form->password, $form->party_id)))){ //Required (optional for integration)
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 12); //We could not validate the password format: - Between 6 and 60 characters
			$errfield = 'password';
		}
		else if(isset($form->email) && !Users::validEmail($form->email)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
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
			if(isset($form->email)){ $model->email = $form->email; } //Optional
			if(isset($form->username)){ $model->username = $form->username; } //Optional
			if(isset($form->firstname)){ $model->firstname = $form->firstname; } //Optional
			if(isset($form->lastname)){ $model->lastname = $form->lastname; } //Optional
			if(isset($form->gender)){ $model->gender = $form->gender; } //Optional
			if(isset($form->profile_pic)){ $model->profile_pic = $form->profile_pic; } //Optional
			if(isset($form->timeoffset)){ $model->timeoffset = $form->timeoffset; } //Optional
			if(isset($form->resume)){ $model->resume = $form->resume; } //Optional
			if($this->createAccount($form, $model, $errmsg, $errfield)){
				$app->render(201, array('show' => false, 'msg' => array('msg' => $app->trans->getBRUT('api', 15, 2)),)); //Account created.
				return true;
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form), 'Account creation failed', __FILE__, __LINE__, true);
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function link_to_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 0, 10)."\n"; //Operation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		//By direct operation, we only allow email account creation, not any integration
		if(isset($form->party) && !empty($form->party)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 34); //We could not validate the format
			$errfield = 'party';
		}
		else if(!isset($form->party_id) || empty($form->party_id) || !Users::validChar($form->party_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 34); //We could not validate the format
			$errfield = 'party_id';
		}
		else if(!isset($form->email) || !Users::validEmail($form->email)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
		}
		//party must be defined in setForm and is null for default Lincko account login system
		else if(empty($form->party) && (!isset($form->party_id) || !isset($form->password) || !Users::validPassword(Datassl::decrypt($form->password, $form->party_id)))){ //Required (optional for integration)
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 12); //We could not validate the password format: - Between 6 and 60 characters
			$errfield = 'password';
		}
		else if($model = Users::getUser()){
			$users_log = UsersLog::WhereNotNull('username_sha1')->Where('username_sha1', $model->username_sha1)->first();
			if($users_log->subAccount($form->party, $form->party_id, $form->password, true, false)){
				$app->render(201, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 15, 37)),)); //Accounts successfully linked.
				return true;
			}
		}
		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form), 'Account failed to link', __FILE__, __LINE__, true);
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function createAccount($data, $user=false, &$errmsg=false, &$errfield=false){
		$app = $this->app;
		$app->lincko->flash['signout'] = true;
		$failmsg = $app->trans->getBRUT('api', 15, 1)."\n"; //Account creation failed.
		if(!$errmsg){
			$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		}
		if(!$errfield){
			$errfield = 'undefined';
		}
		if(!$user){
			$user = new Users;
		}
		if(isset($data->party_id)){

			//Help the frontend to display a waiting
			if(
				   !empty($data->party)
				&& isset($data->integration_code)
				&& strlen($data->integration_code)==8
				&& $integration = Integration::find($data->integration_code)
			){
				$integration->processing = true;
				$integration->save();
			}

			//Used for closed invitation
			if($app->lincko->data['need_invitation'] && isset($data->invitation_code)){
				$app->lincko->flash['unset_invitation_code'] = true;
				if($invitation = Invitation::withTrashed()->where('code', $data->invitation_code)->first()){
					if($invitation->used){
						$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 29); //Invitation code already used.
						goto failed;
					}
				} else {
					$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 29); //Invitation code already used.
					goto failed;
				}
			}

			$json = null;
			if(empty($data->party)){ //Email
				$email = mb_strtolower($user->email);
				if(UsersLog::Where('party', null)->where('party_id', $email)->first() || Users::where('email', $email)->first()){
					$errmsg = $failmsg.$app->trans->getBRUT('api', 15, 10); //Email address already in use.
					$errfield = 'email';
					goto failed;
				}
				if(!isset($user->username) || empty($user->username)){
					$user->username = mb_strstr($email, '@', true);
				}
			} else if($data->party=='wechat'){ //Wechat
				$json = $data->data;
				if(!isset($json->nickname)){ //We exit if we are only on base mode and no user logged
					goto failed;
				}
				$user->username = $json->nickname;
				$user->gender = 0; //Male
				if($json->sex==2){ $user->gender = 1; } //Female
				$translation = new Translation;
				$translation->getList('default');
				if($language = $translation->setLanguage($json->language)){
					$user->language = $language;
				}
			}

			if(!isset($user->username)){
				$user->username = $app->trans->getBRUT('api', 15, 36); //User
			}

			$prefix = '';
			if(!empty($data->party)){
				$prefix = $data->party.'.';
			}
			$user->internal_email = $prefix.md5(uniqid());
			$username_sha1 = sha1($user->internal_email);
			$username_sha1 = substr($username_sha1, 0, 20);
			while(Users::Where('internal_email', $user->internal_email)->orWhere('username_sha1', $username_sha1)->first()){
				usleep(10000);
				$user->internal_email = $prefix.md5(uniqid());
				$username_sha1 = sha1($user->internal_email);
				$username_sha1 = substr($username_sha1, 0, 20);
			}
			$user->username_sha1 = $username_sha1;
			$user->pivots_format($data, false);


			$users_log = new UsersLog;
			$log = md5(uniqid());
			while(UsersLog::Where('log', $log)->first(array('log'))){
				usleep(10000);
				$log = md5(uniqid());
			}
			$users_log->log = $log;
			$users_log->party = $data->party;
			$users_log->party_id = $data->party_id;
			$users_log->username_sha1 = $username_sha1;
			if(isset($data->password) && !empty($data->password) && empty($data->party)){ //Set a password only for email accounts
				$users_log->password = password_hash(Datassl::decrypt($data->password, $data->party_id), PASSWORD_BCRYPT);
			}
			if(is_object($json)){
				$users_log->party_json = json_encode($json, JSON_UNESCAPED_UNICODE);
			}

			$app->lincko->data['create_user'] = true; //Authorize user account creation
			$committed = false;
			if($user->getParentAccess()){
				//Transaction is slowing down a lot the database
				//$db_data = Capsule::connection($app->lincko->data['database_data']);
				//$db_data->beginTransaction();
				//$db_api = Capsule::connection('api');
				//$db_api->beginTransaction();
				try {
					$user->saveHistory(false);
					$user->save();
					$users_log->save();
					Projects::setPersonal();
					if($data->party=='wechat'){
						if(isset($json->account) && isset($json->openid) && !empty($json->openid)){ //Wechat
							$users_log->subAccount('wechat_'.$json->account, 'oid.'.$json->account.'.'.$json->openid, false, false, true);
						}
						//Add profile picture
						if(isset($json->headimgurl)){
							//CURL is much faster tha file_get_contents because file_get_contents was waiting the connection to timeout
							$timeout = 8;
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, $json->headimgurl);
							curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
							curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
							curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
							curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
							curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
							if($download = curl_exec($ch)){
								$tmp_name = '/tmp/'.$user->internal_email;
								file_put_contents($tmp_name, $download);
								$profile_pic = new Files;
								$profile_pic->name = $user->username;
								$profile_pic->ori_type = mime_content_type($tmp_name);
								$profile_pic->tmp_name = $tmp_name;
								$profile_pic->error = 0;
								$profile_pic->size = filesize($tmp_name);
								$profile_pic->parent_type = 'users';
								$profile_pic->parent_id = $app->lincko->data['uid'];
								if($profile_pic->save()){
									$user->profile_pic = $profile_pic->id;
									$user->saveHistory(false);
									$user->save();
								}
								@unlink($tmp_name);
							}
							@curl_close($ch);
						}
					}
					//$db_data->commit();
					//$db_api->commit();
					$committed = true;
				} catch(\Exception $e){
					$committed = false;
					//$db_api->rollback();
					//$db_data->rollback();
					if(isset($user->id)){
						$user->username = 'failed';
						$user->username_sha1 = null;
						$user->email = null;
						$user->internal_email = null;
						$user->brutSave();
					}
					if(isset($users_log->log)){
						$users_log->username_sha1 = null;
						$users_log->party = null;
						$users_log->party_id = null;
						$users_log->save();
					}
					\libs\Watch::php($e, 'Account creation failed', __FILE__, __LINE__, true);
				}
			}

			if($committed){

				$app->lincko->data['uid'] = $user->id;
				$app->lincko->flash['signout'] = false;
				$app->lincko->flash['resignin'] = false;

				//Setup public and private key
				$authorize = $users_log->getAuthorize($this->data);

				$onboarding = new Onboarding;
				$onboarding->next(10101); //initialize the onboarding process

				//Send congrat email
				$link = 'https://'.$app->lincko->domain;
				$title = $app->trans->getBRUT('api', 1003, 1); //Congratulations on joining Lincko!
				$content_array = array(
					'mail_username' => $user->username,
					'mail_link' => $link,
				);
				if(empty($users_log->party) && Users::validEmail($users_log->party_id)){
					$content = $app->trans->getBRUT('api', 1003, 2, $content_array); //Congratulations on joining Lincko. Here’s a link to help you start using Lincko and get on with your journey....
					$inform = new Inform($title, $content, false, $user->getSha(), false, array('email'));
				} else {
					$content = $app->trans->getBRUT('api', 1003, 3); //We are currently in Beta and are hoping to get a lot of feedback to make...
					$inform = new Inform($title, $content, false, $user->getSha(), false, array($users_log->party), array('email')); //Make sure we exclude email
				}
				$inform->send();

				return array(
					$user,
					$users_log,
				);
			}
		}

		failed:
		//Hide the password to avoid hacking
		if(isset($data->password)){
			$data->password = '******';
			unset($data->password);
		}
		\libs\Watch::php(array($errmsg, $data), 'Account creation failed', __FILE__, __LINE__, true);
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
					$model->enableTrash(false);
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

		if($authorize = (new UsersLog)->getAuthorize($data)){
			$msg = $app->trans->getBRUT('api', 15, 13); //You are already signed in.
			if(isset($authorize['private_key'])){
				$msg = $app->trans->getBRUT('api', 15, 14); //Your session has been extended.
			}
			if(isset($authorize['public_key'])){
				$msg = $app->trans->getBRUT('api', 15, 15); //Hello @@user_username~~, you are signed in to your account.
			}
			if(isset($authorize['refresh'])){
				if($authorize['refresh']){
					$msg = $app->trans->getBRUT('api', 15, 14); //Your session has been extended.
				} else {
					$msg = $app->trans->getBRUT('api', 15, 15); //Hello @@user_username~~, you are signed in to your account.
				}
			}
			$app->render(200, array('msg' => array('msg' => $msg)));
			return true;
		} else {
			if(!isset($form->party) || empty($form->party)){ //Email/speudo
				if(isset($form->party_id) && UsersLog::Where('party', null)->where('party_id', $form->party_id)->first(array('log'))){
					$errmsg = $app->trans->getBRUT('api', 15, 35); //Your user id or password is incorrect
				} else {
					$errmsg = $app->trans->getBRUT('api', 15, 35); //Your user id or password is incorrect
				}
			} else if(isset($form->party_id) && !UsersLog::Where('party', $dform->party)->where('party_id', $form->party_id)->first(array('log'))){ //Integration
				$errmsg = $app->trans->getBRUT('api', 15, 35); //Your user id or password is incorrect
			}
		}

		//Hide the password to avoid hacking
		if(isset($form->password)){
			$form->password = '******';
		}
		\libs\Watch::php(array($errmsg, $form), 'Sign in failed', __FILE__, __LINE__, true);
		$app->lincko->flash['signout'] = true;
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

		if($authorization = Authorization::find_finger($data->public_key, $data->fingerprint)){
			$authorization->delete();
			$msg = $app->trans->getBRUT('api', 15, 17); //You have signed out of your account.
		}

		$app->lincko->flash['signout'] = true;
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
			$user = false;
			if($user_log = UsersLog::Where('party', null)->where('party_id', $email)->first(array('username_sha1'))){
				if($user = Users::where('username_sha1', $user_log->username_sha1)->first()){ //Account found
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
					$data->updated_at = $user->updated_at->getTimestamp();
					$data->profile_pic = $user->profile_pic;
					$msg = $app->trans->getBRUT('api', 15, 23); //Account found
					$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
					return true;
				}
			}
			if(!$user){
				$data = true;
				$msg = $app->trans->getBRUT('api', 15, 24); //Account not found
				$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function find_qrcode_post(){
		$app = $this->app;

		$failmsg = $app->trans->getBRUT('api', 15, 3)."\n"; //Account access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		$form = $this->form;
		if(is_array($form) || is_object($form)){
			foreach ($form as $key => $value) {
				$id = Datassl::decrypt($value, 'invitation');
				break; //Insure to take the first one only
			}
			if($id>1 && $user = Users::find($id)){ //Account found
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
				$data->updated_at = $user->updated_at->getTimestamp();
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
				if($user_log = UsersLog::Where('party', null)->where('party_id', $email)->first(array('username_sha1'))){
					$guest = Users::where('username_sha1', $user_log->username_sha1)->first();
					if(!$guest){
						$user = Users::getUser();
						$username = $user->username;
						$invitation = new Invitation();
						if(isset($form->invite_access)){
							$invitation->models = $form->invite_access;
						}
						$invitation->save();

						$code = $invitation->code;
						$link = 'https://'.$app->lincko->domain.'/invitation/'.$code;
						$title = $app->trans->getBRUT('api', 1001, 1); //Your invitation to join Lincko
						$content_array = array(
							'mail_username' => $username,
							'mail_link' => $link,
						);
						$content = $app->trans->getBRUT('api', 1001, 2, $content_array); //Hello,@@username~~ has invited you to join Lincko. Lincko helps you accomplish great....
						$annex = $app->trans->getBRUT('api', 1001, 3); //You are receiving this e-mail because someone invited you to collaborate together using Lincko.
						$manual = array(
							'email' => array(
								$form->email => $username,
							),
						);
						$inform = new Inform($title, $content, $annex, array(), false, array('email'));
						$inform->send($manual);

						$data = true;
						$msg = $app->trans->getBRUT('api', 15, 26); //Invitation sent
						$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
						return true;
					}
				}
			} else if($form->exists && isset($form->users_id)){
				if($guest = Users::find($form->users_id)){
					if(Users::inviteSomeone($guest, $form)){
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

	public function inviteqrcode_post(){
		$app = $this->app;
		$form = $this->form;

		if(Users::inviteSomeoneCode($form)){
			$data = true;
			$msg = $app->trans->getBRUT('api', 15, 26); //Invitation sent
			$app->render(200, array('msg' => array('msg' => $msg, 'data' => $data)));
			return true;
		}
		$msg = $app->trans->getBRUT('api', 15, 25); //Invitation failed to send.
		$app->render(200, array('msg' => array('msg' => $msg, 'data' => false)));
		return true;
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

		if(!isset($form->party_id) || !Users::validEmail($form->party_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 35); //E-mail address format incorrect
			$errfield = 'email';
		}
		else if($user_log = UsersLog::Where('party', null)->where('party_id', mb_strtolower($form->party_id))->first()){
			if($user = Users::Where('username_sha1', $user_log->username_sha1)->first()){
				$user_log->code = substr(str_shuffle("123456789"), 0, 6);
				$limit = Carbon::now();
				$limit->second = $limit->second + 1210; //We give 20 minutes to enter the code (including 10 more seconds to cover communication latency)
				$user_log->code_limit = $limit;
				$user_log->code_try = 3; //We give 3 shots to success
				if($user_log->save()){
					$title_email = $app->trans->getBRUT('api', 1004, 1); //Password reset
					$title_other = $app->trans->getBRUT('api', 1004, 3); //You have requested a password reset.
					$content_array = array(
						'mail_username' => $user->username,
						'mail_code' => $user_log->code,
					);
					$content_email = $app->trans->getBRUT('api', 1004, 2, $content_array); //You have requested a password reset. You need to enter the code below within 10 minutes in the required field to be able to confirm the operation. CODE: <b>@@mail_code~~<b/>
					$content_other = $app->trans->getBRUT('api', 1004, 4, $content_array); //CODE: @@mail_code~~

					$inform_email = new Inform($title_email, $content_email, false, $user->getSha(), false, array('email')); //Only email
					$inform_email->send();
					$inform_other = new Inform($title_other, $content_other, false, $user->getSha(), false, array(), array('email')); //Exclude email
					$inform_other->send();

					$msg = $app->trans->getBRUT('api', 15, 33); //You will receive an email with a Code.
					$app->render(200, array('show' => true, 'msg' =>  array('msg' => $msg, 'email' => $user->email), 'error' => false));
					return true;
				}
			}	
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function reset_post(){
		$app = $this->app;
		$form = $this->form;
		$data = $this->data;

		$reset = false;

		$failmsg = $app->trans->getBRUT('api', 0, 10)."\n"; //Operation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->party_id) || empty($form->party_id) || !Users::validEmail($form->party_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 35); //E-mail address format incorrect
			$errfield = 'email';
		}
		else if(!isset($form->code) || !Users::validCode($form->code)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 31); //Please enter the correct code
			$errfield = 'code';
		}
		else if(!isset($form->password) || !Users::validPassword(Datassl::decrypt($form->password, $form->party_id))){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 12); //We could not validate the password format: - Between 6 and 60 characters
			$errfield = 'password';
		}
		else if($user_log = UsersLog::where('party', null)->where('party_id', mb_strtolower($form->party_id))->WhereNotNull('code')->first()){
			if($user = Users::where('username_sha1', $user_log->username_sha1)->first()){
				if($user_log->code == $form->code){
					$now = time();
					$code_limit = (new \DateTime($user_log->code_limit))->getTimestamp();
					if($code_limit >= $now){
						$user_log->old_password = $user_log->password; //Just in case, keep the old password in memory
						$user_log->password = password_hash(Datassl::decrypt($form->password, $form->party_id), PASSWORD_BCRYPT);
						$user_log->code = null;
						$user_log->code_limit = null;
						$user_log->code_try = 0;
						if($user_log->save()){
							$title = $app->trans->getBRUT('api', 1005, 1); //Password reset - Confirmation
							$link = 'https://'.$app->lincko->domain;
							$content_array = array(
								'mail_username' => $user->username,
								'mail_link' => $link,
							);
							$content = $app->trans->getBRUT('api', 1005, 2, $content_array); //You have successfully reset your password. You can now signin.
							$inform = new Inform($title, $content, false, $user->getSha());
							$inform->send();
							
							$authorize = $user_log->getAuthorize($data);

							//Hide the password to avoid hacking
							if(isset($form->password)){
								$form->password = '******';
							}

							$msg = $app->trans->getBRUT('api', 15, 34); //Password successfully reset
							$app->render(200, array('show' => true, 'msg' =>  array('msg' => $msg), 'error' => false));

							//$json = new Json($msg, false, 200, false, false, array(), true);
							//$json->render(200);

							return true;
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
			$errmsg = $app->trans->getBRUT('api', 0, 10); //Operation failed.
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield, 'reset' => $reset), 'error' => true));
		return false;
	}

}
