<?php

namespace bundles\lincko\api\models;

use \libs\Datassl;
use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\data\Users;

class Integration extends Model {

	protected $connection = 'api';

	protected $table = 'integration';

	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	public $timestamps = true;

	protected $visible = array();

	protected static $data = null;

	protected static $integration = false;

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public static function find($id, $columns = ['*']){
		return false;
	}

	public static function getIntegration(){
		return self::$integration;
	}

	
	public static function check($data){
		//\libs\Watch::php($data, '$Integration', __FILE__, __LINE__, false, false, true);
		$app = \Slim\Slim::getInstance();
		$valid = false;
		if(isset($data->data) && isset($data->data->party) && isset($data->data->party_id) && isset($data->data->data)){
			$json = $data->data->data;
			//If integration exists
			if($integration = self::Where('party', $data->data->party)->where('party_id', $data->data->party_id)->first()){
				self::$integration = $integration;
			}
			//If new integration, we create a user account
			else {
				$integration = new Integration;
				$integration->party = $data->data->party;
				$integration->party_id = $data->data->party_id;
				$integration->json = json_encode($data->data->data);
				$param = new \stdClass;
				$param->email = $data->data->party.'.'.md5($data->data->party_id.md5(uniqid())).'@'.$app->lincko->domain;
				usleep(10000);
				$param->password = Datassl::encrypt(md5($data->data->party_id), $param->email);
				$creation = false;
				if($data->data->party=='wechat'){ //Wechat
					$param->username = $json->nickname;
					$param->gender = 0; //Male
					if($json->sex==2){ $param->gender = 1; } //Female
					$creation = self::createUser($data, $param);
				}
				if(
					   $creation
					&& isset($creation->status)
					&& $creation->status==201
					&& isset($creation->flash)
					&& isset($creation->flash->uid)
					&& $creation->flash->uid > 0
				){
					$integration->users_id = $creation->status->flash->uid;
					if($integration->save()){
						$valid = true;
					}
				}
			}
			if($valid){
				self::$integration = $integration;
			}
		}
		return $valid;
	}

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
	

}
