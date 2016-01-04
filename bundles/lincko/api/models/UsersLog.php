<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;

class UsersLog extends Model {

	protected $connection = 'api';

	protected $table = 'users_log';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array();

////////////////////////////////////////////

	//One(UsersLog) to One(Users)
	//Warning: This does not work because the 2 tables are in 2 different databases
	public function users(){
		return $this->hasOne('\\bundles\\lincko\\api\\models\\data\\Users', 'username_sha1');
	}

////////////////////////////////////////////

	//Do not call noValidMessage because it's not a child of ModleLincko, but Model directly
	public static function validPassword($data){
		$return = preg_match("/^[\w\d]{6,60}$/u", $data);
		return $return;
	}

	public static function isValid($form){
		return
			     isset($form->password) && self::validPassword($form->password)
			;

	}

////////////////////////////////////////////

	public function authorize($data){
		$app = \Slim\Slim::getInstance();

		$authorize = false;
		$refresh = false;
		$authorization = false;
		$fingerprint = null;

		if(isset($data->fingerprint)){
			$fingerprint = $data->fingerprint;
		} else {
			return false;
		}

		if(isset($data->public_key) && $authorization = Authorization::find_finger($data->public_key, $fingerprint)){
			//If we are signing in as a new user, we force to recheck the password.
			if($this->id!==$authorization->user_id){
				$authorization = false;
			}
		}

		if($authorization){
			//If we are at half of expiration time, we renew the secret_key, but we have to keep the old security_key to avoid any call bug (quick two-clicks)
			$expired = new \DateTime($authorization->updated_at);
			$half_expired = ceil($app->lincko->security['expired']/2);
			$expired->add(new \DateInterval('PT'.$half_expired.'S'));
			$now = new \DateTime();
			if($expired >= $now){
				return true;
			} else if($expired < $now || (isset($data->data->password) && password_verify($data->data->password, $this->password))){
				$authorize = true;
				$refresh = true;
			}
		} else if(isset($data->data->password) && password_verify($data->data->password, $this->password)){
			$authorize = true;
			Authorization::clean();
		}

		if($authorize){

			$authorization = new Authorization;
			$sha1 = sha1(uniqid());
			while(!is_null(Authorization::where('public_key', '=', $sha1)->first())){
				$sha1 = sha1(uniqid());
			}
			//Warning: $authorization->public_key will reset to empty value after save because it's a primary value
			$public_key = $authorization->public_key = $sha1;
			$authorization->private_key = md5(uniqid());
			$private_key = $authorization->private_key;
			$authorization->user_id = $this->id;
			$authorization->fingerprint = $fingerprint;
			if($authorization->save()){
				return array(
					'public_key' => $public_key,
					'private_key' => $private_key,
					'username_sha1' => $this->username_sha1,
					'uid' => Users::where('username_sha1', '=', $this->username_sha1)->first()->id,
					'refresh' => $refresh ,
				);
			}
		}

		return false;
	}

}