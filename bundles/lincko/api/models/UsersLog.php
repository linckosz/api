<?php

namespace bundles\lincko\api\models;

use \libs\Datassl;
use \libs\Translation;
use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\Integration;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\Notif;
use \bundles\lincko\api\models\Onboarding;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\controllers\ControllerUser;

class UsersLog extends Model {

	const SALT = 'ayTgh49pW09w';

	protected $connection = 'api';

	protected $table = 'users_log';

	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	protected $primaryKey = 'log';

	public $timestamps = true;

	protected $visible = array(
		'username_sha1',
	);

	protected static $data = null;

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

////////////////////////////////////////////

	/*
		log: credential fixed id
		party: null(email), wechat, facebook, etc
		party_id: email_add, wechat_id, facebook_id, etc
	*/
	protected function checkCredential($data){
		Authorization::clean();
		$item = false;
		$party = null; // email/speudo by default
		if(!isset($data->data) || (!isset($data->data->party_id) && !isset($data->data->log_id))){
			return false;
		}
		if(isset($data->data->party) && !empty($data->data->party)){
			$party = $data->data->party;
		}
		if(isset($data->data->party_id) && !empty($data->data->party_id)){
			$item = self::Where('party', $party)->whereNotNull('party_id')->where('party_id', $data->data->party_id)->first();
			if(is_null($party) && $item){
				//If it's by email/speudo, we double check the password
				if(!isset($data->data->password) || !password_verify(Datassl::decrypt($data->data->password, $data->data->party_id), $item->password)){
					$item = false;
				}
			}
		} else if(isset($data->data->log_id)){
			$log_id = Datassl::decrypt($data->data->log_id, 'log_id');
			$item = self::find($log_id);
		}

		return $item;
	}

	public function getPukpic(){
		$app = \Slim\Slim::getInstance();
		$expiration = $app->lincko->cookies_lifetime;
		return Datassl::encrypt($expiration.':'.$this->log, self::SALT);
	}

	public static function pukpicToSha($shangzai_puk=false){
		$log = false;
		$pukpic = false;
		if(!$pukpic && isset($_COOKIE) && isset($_COOKIE['pukpic']) && !empty($_COOKIE['pukpic'])){
			$pukpic = $_COOKIE['pukpic'];
		} else if($shangzai_puk){
			$pukpic = Datassl::decrypt($shangzai_puk);
		}
		if($pukpic){
			$pukpic = Datassl::decrypt($pukpic, self::SALT);
			if(preg_match("/^(\d+):(\w+)$/ui", $pukpic, $matches)){
				if(time() < $matches[1]){ //not expired
					$log = $matches[2];
				}
			}
		}
		if($log && $users_log = self::Where('log', $log)->first(array('username_sha1'))){
			return $users_log->username_sha1;
		}
		return false;
	}

	public function getAuthorize($data){
		$app = \Slim\Slim::getInstance();

		$refresh = false;
		$authorization = false;
		$users_log = false;
		$new_log = true;

		//The devce must have a fingerprint
		if(!isset($data->fingerprint)){
			return false;
		}

		if(isset($this->log) && isset($data->public_key) && $authorization = Authorization::find_finger($data->public_key, $data->fingerprint)){
			//If we are signing in as a new user, we force to recheck the credential.
			if($this->log!==$authorization->log_id){
				$authorization = false;
			} else { //it's a "refresh" of the same user
				$new_log = false;
				$users_log = $this;
			}
		}

		if($authorization){
			//If we are at half of expiration time, we renew the secret_key, but we have to keep the old security_key to avoid any call bug (quick two-clicks)
			//toto => an rewrite this rule with Carbon
			$expired = new \DateTime($authorization->updated_at);
			$half_expired = ceil($app->lincko->security['expired']/2);
			$expired->add(new \DateInterval('PT'.$half_expired.'S'));
			$now = new \DateTime();
			if($expired >= $now){ //Renew
				$authorization = new Authorization;
			}
			$refresh = true;
		} else if($users_log = $this->checkCredential($data)){
			$authorization = new Authorization;
		}

		if($users_log && $authorization){
			$sha1 = sha1(uniqid());
			while(!is_null(Authorization::where('public_key', '=', $sha1)->first(array('public_key')))){
				$sha1 = sha1(uniqid());
			}
			//Warning: $authorization->public_key will reset to empty value after save because it's a primary value
			$public_key = $authorization->public_key = $sha1;
			$private_key = $authorization->private_key = md5(uniqid());
			$authorization->fingerprint = $data->fingerprint;
			$user = Users::where('username_sha1', '=', $users_log->username_sha1)->first(array('id', 'username'));
			$authorization->sha = $users_log->username_sha1;
			if($user &&	$authorization->save()){
				$app->lincko->translation['user_username'] = $user->username;
				$arr = array(
					'public_key' => $public_key,
					'pukpic' => $users_log->getPukpic(),
					'private_key' => $private_key,
					'username_sha1' => substr($users_log->username_sha1, 0, 20), //Truncate to 20 character because phone alias notification limitation
					'uid' => $user->id,
					'username' => $user->username,
					'refresh' => $refresh,
				);
				//If it's a new login we send back users_log ID encrypted for cookies (make sure the cookie is refreshed)
				if($new_log){
					$arr['log_id'] = Datassl::encrypt($users_log->log, 'log_id');
				}
				foreach ($arr as $key => $value) {
					$app->lincko->flash[$key] = $value;
				}
				return $arr;
			}
		}

		return false;
	}

	public static function check($data){
		$app = \Slim\Slim::getInstance();
		$log_id = false;
		$invitation = false;
		if(isset($data->data) && isset($data->data->party) && isset($data->data->party_id) && !empty($data->data->party) && !empty($data->data->party_id) && isset($data->data->data)){
			$json = $data->data->data;
			//If users_log exists
			if($users_log = self::Where('party', $data->data->party)->whereNotNull('party_id')->where('party_id', $data->data->party_id)->first()){
				return $users_log->id;
			}
			//We exit if we are only on base mode and no user logged
			else if($data->data->party=='wechat' && !isset($json->nickname)){
				return false;
			}
			//If new users_log, we create a user account
			else {
				$file = false;
				$user = new Users;
				$user->username = 'Lincko user';
				$user->internal_email = $data->data->party.'.'.$data->data->party_id;
				$limit = 0;
				$username_sha1 = sha1($user->internal_email);
				$username_sha1 = substr($username_sha1, 0, 20);
				$accept = false;
				while( $limit <= 1000 && Users::Where('internal_email', $user->internal_email)->orWhere('username_sha1', $username_sha1)->first() ){
					usleep(10000);
					$user->internal_email = $data->data->party.'.'.md5(uniqid());
					$username_sha1 = sha1(uniqid());
					$username_sha1 = substr($username_sha1, 0, 20);
					$limit++;
				}
				if($limit < 1000){
					$accept = true;
				}
				$user->username_sha1 = $username_sha1;
				$users_log = new UsersLog;
				$log = md5(uniqid());
				while(UsersLog::Where('log', $log)->first(array('log'))){
					usleep(10000);
					$log = md5(uniqid());
				}
				$users_log->log = $log;
				$users_log->party = $data->data->party;
				$users_log->party_id = $data->data->party_id;
				$users_log->party_json = json_encode($data->data->data, JSON_UNESCAPED_UNICODE);
				$users_log->username_sha1 = $username_sha1;
				if($data->data->party=='wechat'){ //Wechat
					$user->username = $json->nickname;
					$user->gender = 0; //Male
					if($json->sex==2){ $user->gender = 1; } //Female
					$translation = new Translation;
					$translation->getList('default');
					if($language = $translation->setLanguage($json->language)){
						$user->language = $language;
					}
					if($json->openid){
						$users_log_bis = new UsersLog;
						$log_bis = md5(uniqid());
						while($log_bis != $log && UsersLog::Where('log', $log_bis)->first(array('log'))){
							usleep(10000);
							$log_bis = md5(uniqid());
						}
						$users_log_bis->log = $log_bis;
						$users_log_bis->party = $data->data->party;
						$users_log_bis->party_id = 'oid.'.$json->openid;
						$users_log_bis->party_json = $users_log->party_json;
						$users_log_bis->username_sha1 = $username_sha1; //Same username_sha1 to allow 2 kind of connection on same account
					}
				}
				$app->lincko->data['create_user'] = true; //Authorize user account creation
				if($accept){
					$committed = false;
					if($user->getParentAccess()){
						if(
							   isset($data->data)
							&& isset($data->data->integration_code)
							&& strlen($data->data->integration_code)==8
							&& $integration = Integration::find($data->data->integration_code)
						){
							$integration->processing = true;
							$integration->save();
						}
						try {
							$user->save();
							$users_log->save();
							if(isset($users_log_bis)){
								$users_log_bis->save();
							}
							Projects::setPersonal();
							$committed = true;
						} catch(\Exception $e){
							$committed = false;
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
							if(isset($users_log_bis) && isset($log_bis) && isset($users_log_bis->log_bis)){
								$users_log_bis->username_sha1 = null;
								$users_log_bis->party = null;
								$users_log_bis->party_id = null;
								$users_log_bis->save();
							}
						}
						if($committed){
							$app->lincko->data['uid'] = $user->id;
							$app->lincko->flash['signout'] = false;
							$app->lincko->flash['resignin'] = false;
							$log_id = $users_log->id;
							$onboarding = new Onboarding;
							$onboarding->next(10101); //initialize the onboarding process
							//Additional account information that need user ID
							if($data->data->party=='wechat'){ //Wechat
								//Add profile picture
								if(isset($json->headimgurl)){
									if($download = file_get_contents($json->headimgurl)){
										$tmp_name = '/tmp/'.$user->internal_email;
										file_put_contents($tmp_name, $download);
										$profile_pic = new Files;
										$profile_pic->name = 'Martin';
										$profile_pic->ori_type = mime_content_type($tmp_name);
										$profile_pic->tmp_name = $tmp_name;
										$profile_pic->error = 0;
										$profile_pic->size = filesize($tmp_name);
										$profile_pic->parent_type = 'users';
										$profile_pic->parent_id = $app->lincko->data['uid'];
										if($profile_pic->save()){
											$user->profile_pic = $profile_pic->id;
											$user->save();
										}
									}
								}
							}

							/*
							$model = $user;
							//Invitation
							if($invitation){
								$pivot = new \stdClass;
								$invitation_models = false;
								if(!is_null($invitation->models)){
									$invitation_models = json_decode($invitation->models);
								}
								//Record for invitation
								$invitation->guest = $model->id;
								$invitation->used = true;
								$invitation->models = null;
								$invitation->save();

								if($invitation->created_by>0 && $user = Users::find($invitation->created_by)){
									//For guest & host
									$pivot->{'users>access'} = new \stdClass;
									$pivot->{'users>access'}->{$user->id} = true;
									//If gave access to some items
									if($invitation_models){
										foreach ($invitation_models as $table => $list) {
											//Don't give access to others users or workspace
											if($table=='workspaces' || $table=='users'){
												continue;
											}
											$pivot->{$table.'>access'} = new \stdClass;
											//Make sure that the host have access to the original item
											//toto => to do
											if(is_numeric($list)){
												$id = intval($list);
												$pivot->{$table.'>access'}->$id = true;
											} else if(is_array($list) || is_object($list)){
												foreach ($list as $id) {
													$id = intval($id);
													$pivot->{$table.'>access'}->$id = true;
												}
											}
										}
									}
									$model->pivots_format($pivot);
									$model->forceSaving();
									$model->save();

									$mail_subject = $app->trans->getBRUT('api', 1004, 5); //Invitation accepted
									$mail_body_array = array(
										'mail_username' => $user->username,
									);
									$mail_body = $app->trans->getBRUT('api', 1004, 6, $mail_body_array); //@@mail_username~~ accepted your invitation.

									//Send mobile notification
									(new Notif)->push($mail_subject, $mail_body, false, $user->getSha());
								}
							}
							*/
						}
					}
				}
					
			}
		}
		return $log_id;
	}

}
