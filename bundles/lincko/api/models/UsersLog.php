<?php

namespace bundles\lincko\api\models;

use \libs\Datassl;
use \libs\Translation;
use \config\Handler;
use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\controllers\ControllerUser;

class UsersLog extends Model {

	protected $connection = 'api';

	protected $table = 'users_log';

	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	protected $primaryKey = 'log';

	public $timestamps = true;

	protected $visible = array();

	protected static $data = null;

	protected static $integration = false;

	protected static $flash = array();

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
		if(isset($data->data->party_id)){
			$item = self::Where('party', $party)->where('party_id', $data->data->party_id)->first();
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
			$app->lincko->securityFlash['public_key'] = $public_key = $authorization->public_key = $sha1;
			$authorization->private_key = md5(uniqid());
			$app->lincko->securityFlash['private_key'] = $private_key = $authorization->private_key;
			$authorization->fingerprint = $data->fingerprint;
			$user = Users::where('username_sha1', '=', $users_log->username_sha1)->first(array('id', 'username'));
			$authorization->sha = $users_log->username_sha1;
			if($user &&	$authorization->save()){
				$app->lincko->translation['user_username'] = $user->username;
				$arr = array(
					'public_key' => $public_key,
					'pukpic' => Datassl::encrypt($public_key, 'public_key_file'),
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
					$app->flashNow($key, $value);
				}
				return $arr;
			}
		}

		return false;
	}

	public static function check($data){
		//\libs\Watch::php($data, '$users_log', __FILE__, __LINE__, false, false, true);
		$app = \Slim\Slim::getInstance();
		$log_id = false;
		if(isset($data->data) && isset($data->data->party) && isset($data->data->party_id) && isset($data->data->data)){
			$json = $data->data->data;
			//If users_log exists
			if($users_log = self::Where('party', $data->data->party)->where('party_id', $data->data->party_id)->first()){
				return $users_log->id;
			}
			//If new users_log, we create a user account
			else {
				$users_log = new Integration;
				$log = md5(uniqid());
				while(UsersLog::Where('log', $log)->first(array('log'))){
					usleep(10000);
					$log = md5(uniqid());
				}
				$users_log->log = $log;
				$users_log->party = $data->data->party;
				$users_log->party_id = $data->data->party_id;
				$users_log->json = json_encode($data->data->data);

				$user = new Users;
				$user->username = 'Lincko user';
				$user->internal_email = $integration->party.'.'.$integration->party_id;
				if($data->data->party=='wechat'){ //Wechat
					$user->username = $json->nickname;
					$user->gender = 0; //Male
					if($json->sex==2){ $user->gender = 1; } //Female
					if($language = (new Translation)->setLanguage($json->language)){
						$user->language = $language;
					}
				}

				$limit = 0;
				$username_sha1 = sha1($user->internal_emai);
				$username_sha1 = substr($username_sha1, 0, 20);
				$accept = false;
				while( $limit <= 1000 && Users::Where('internal_email', $user->internal_email)->orWhere('username_sha1', $username_sha1)->first() ){
					sleep(10000);
					$username_sha1 = sha1(uniqid());
					$username_sha1 = substr($username_sha1, 0, 20);
					$limit++;
				}
				if($limit < 1000){
					$accept = true;
				}
				$user->username_sha1 = $username_sha1;
				$users_log->username_sha1 = $username_sha1;
				if($accept && $user->save() && $users_log->save()){
					$log_id = $users_log->id;
				}
			}
		}
		return $log_id;
	}

	/*
	protected static function createUser($data, $param){
		$app = \Slim\Slim::getInstance();

		

		$data->data = $param;
		$data->public_key = $app->lincko->security['public_key']; //Use public key for account creation
		$data->checksum = md5($app->lincko->security['private_key'].json_encode($data->data, JSON_UNESCAPED_UNICODE));

		$url = 'https://'.$_SERVER['HTTP_HOST'].'/user/create';

		$data = json_encode($data);
		$timeout = 8;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url); //Port used is 10443 only
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json; charset=UTF-8',
				'Content-Length: ' . mb_strlen($data),
			)
		);

		$verbose = fopen('php://temp', 'w+');
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $verbose);

		if($result = curl_exec($ch)){
			$result = json_decode($result);
		} else {
			\libs\Watch::php(curl_getinfo($ch), '$ch', __FILE__, __LINE__, false, false, true);
			$error = '['.curl_errno($ch)."] => ".htmlspecialchars(curl_error($ch));
			\libs\Watch::php($error, '$error', __FILE__, __LINE__, false, false, true);
			rewind($verbose);
			\libs\Watch::php(stream_get_contents($verbose), '$verbose', __FILE__, __LINE__, false, false, true);
			fclose($verbose);
		}

		@curl_close($ch);
		return $result;
	}
	*/

}
