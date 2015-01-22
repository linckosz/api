<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\Authorization;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Users extends Model {

	protected $connection = 'api';

	protected $table = 'users';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'username',
	);
	
////////////////////////////////////////////

	public static function validUsername($username){
		return preg_match("/^\S{1,104}$/u", $username);
	}

	public static function validPassword($password){
		return preg_match("/^[\w\d]{6,60}$/u", $password);
	}

	public static function validEmail($email){
		return preg_match("/^.{1,191}$/u", $email) && preg_match("/^.{1,100}@.*\..{2,4}$/ui", $email) && preg_match("/^[_a-z0-9-%+]+(\.[_a-z0-9-%+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", $email);
	}

	public static function isValid($category, $form){
		if($category==='user_signin'){
			return
				   isset($form->email)
				&& isset($form->password)
				&& self::validEmail($form->email)
				&& self::validPassword($form->password)
				;
		} else if($category==='user_create'){
			return
				   isset($form->email)
				&& isset($form->password)
				&& self::validEmail($form->email)
				&& self::validPassword($form->password)
				;
		}
		return false;
	}

	public function authorize($data){
		$app = $this->app = \Slim\Slim::getInstance();

		$authorize = false;
		$refresh = false;

		if(isset($data->public_key) && $authorization = Authorization::find($data->public_key)){
			//If we are at half of expiration time, we renew the scret_key, but we have to keep the old security_key to avoid any call bug (quick two-clicks)
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
			if($authorization->save()){
				return array(
					'public_key' => $public_key,
					'private_key' => $private_key,
					'refresh' => $refresh ,
				);
			}
		}

		return false;
	}

}