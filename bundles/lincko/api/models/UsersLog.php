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
use \bundles\lincko\api\models\libs\ModelLincko;

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
		$app = ModelLincko::getApp();
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
		$app = ModelLincko::getApp();

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
			while(!is_null(Authorization::where('public_key', $sha1)->first(array('public_key')))){
				$sha1 = sha1(uniqid());
			}
			//Warning: $authorization->public_key will reset to empty value after save because it's a primary value
			$public_key = $authorization->public_key = $sha1;
			$private_key = $authorization->private_key = md5(uniqid());
			$authorization->fingerprint = $data->fingerprint;
			$user = Users::where('username_sha1', $users_log->username_sha1)->first(array('id', 'username'));
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

	public function subAccount($party, $party_id, $password=false, $merge=false, $force=false){
		$result = false;
		if(empty($password) || !empty($party)){ //Make sure we convert false and null
			$password = ''; //Password exists only for email login
		} else {
			$password = password_hash(Datassl::decrypt($data->password, $data->party_id), PASSWORD_BCRYPT);
		}
		if(!$force && $party == $this->party){
			return false; //We do not allow 2 similar methods of connection
		} else if(empty($party_id)){
			return false; //We reject all kind of empty party_id
		} else if(empty($party) && (!Users::validEmail($party_id) || !Users::validPassword(Datassl::decrypt($password, $party_id)))){
			return false; //We reject Lincko credential of the party_id is not an email format or the password is missing
		}
		$model = self::Where('party', $party)->whereNotNull('party_id')->where('party_id', $party_id)->first(array('log'));
		
		//If the second credential information does not exists, we attach a new one
		if(!$model){
			$model = $this->replicate();
			$log = md5(uniqid());
			while($log != $this->log && UsersLog::Where('log', $log)->first(array('log'))){
				usleep(10000);
				$log = md5(uniqid());
			}
			$model->log = $log;
			$model->party_id = $party_id;
			$model->password = $password;
			if($model->save()){
				$result = $model;
			}
		}
		//If the user wants to merge two accounts, we import all links from 
		else if($merge && $model->username_sha1 != $this->username_sha1){
			//check if the importation is possible
			$list_from = array();
			$list = self::Where('username_sha1', $model->username_sha1)->get(array('party'));
			foreach ($list as $value) {
				$list_from[] = $value->party;
			}
			$list = self::Where('username_sha1', $this->username_sha1)->get(array('party'));
			foreach ($list as $value) {
				if(in_array($value->party, $list_from)){
					return false; //We reject if one of both group has similar party since we don't allow 2 similar party to the same account ( user_1 [email_1] wants wechat_1, but it's already attached by user_2 [email_2, wechat_1] which already has an email account )
				}
			}

			$user = Users::WhereNotNull('username_sha1')->where('username_sha1', $this->username_sha1)->first();
			$import_user = Users::WhereNotNull('username_sha1')->where('username_sha1', $model->username_sha1)->first();
			if($user && $import_user && $user->import($import)){
				$model = $this->username_sha1;
				$model->password = $password;
				if($model->save()){
					$result = $model;
				}
			}
		} else {
			$result = $model;
		}
		return $result;
	}

	public static function check_integration($data){
		$app = ModelLincko::getApp();
		if(isset($data->data) && isset($data->data->party) && isset($data->data->party_id) && !empty($data->data->party) && !empty($data->data->party_id) && isset($data->data->data)){
			$json = $data->data->data;
			//If users_log exists
			if($users_log = self::Where('party', $data->data->party)->whereNotNull('party_id')->where('party_id', $data->data->party_id)->first()){
				//For Wechat, if we log with union id, double check that the openid is registered too
				if($data->data->party=='wechat' && substr($data->data->party_id, 0, 4)=='uid.'){ //Wechat
					if(isset($json->openid) && !empty($json->openid)){
						$users_log->subAccount('wechat', 'oid.'.$json->openid, false, false, true);
					}
				}
				return $users_log;
			}
			//If new users_log, we create a user account
			else {
				$controller_user = new ControllerUser;
				if($result = $controller_user->createAccount($data->data)){
					return $result[1];
				}
					
			}
		}
		return false;
	}

}
