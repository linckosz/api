<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\data\Users;

class Integration extends Model {

	protected $connection = 'api';

	protected $table = 'integration';

	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	public $timestamps = true;

	protected $visible = array();

	protected $data = null;

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public static function find($id, $columns = ['*']){
		return false;
	}

	/*
	public static function check($data){
		\libs\Watch::php($data, '$Integration', __FILE__, __LINE__, false, false, true);
		$valid = false;
		if(isset($data->data) && isset($data->data->party) && isset($data->data->party_id) && isset($data->data->data)){
			//If integration exists
			if($integration = self::Where('party', $data->data->party)->where('party_id', $data->data->party_id)->first()){
				
			}
			//If new integration, we create a user account
			else {
				if($data->data->party=='wechat'){
					$user = new Users;
				}

			}
			self::$data = $data->data->data;
			if($data->data->party==''){

			}
			if($valid){
				Users::$integration = $integration;
			}
		}
		return $valid;
	}

	public static function createUser($param){
		$app = $this->app;

		$data = json_decode($app->request->getBody());
		$data->checksum

		if(!is_object($data)){
			return false;
		}
		$data = json_encode($data);
		\libs\Watch::php($data, $url, __FILE__, __LINE__, false, false, true);
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
			\libs\Watch::php(json_decode($result), '$result', __FILE__, __LINE__, false, false, true);
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

class Wechat {
	
}
