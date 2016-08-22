<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

class Authorization extends Model {

	protected $connection = 'api';

	protected $table = 'authorization';

	protected $primaryKey = 'public_key';

	public $timestamps = true;

	protected $visible = array();

	/////////////////////////////////////

	public static function clean($users_id=NULL){
		$app = \Slim\Slim::getInstance();
		$limit = new \DateTime();
		$limit->sub(new \DateInterval('PT'.$app->lincko->security['expired'].'S'));
		
		if($users_id){
			//Only delete own user authorization records
			return self::where('users_id', '=', $users_id)->where('updated_at', '<', $limit)->delete();
		} else {
			//Delete all users expired (force expired account to resign with Email/Password)
			return self::where('updated_at', '<', $limit)->delete();
		}
	}

	public static function find_finger($public_key, $fingerprint){
		return self::where('public_key', $public_key)->where('fingerprint', $fingerprint)->first();
	}

	public static function getPublicKey($users_id, $fingerprint, $public_key){
		return self::where('users_id', $users_id)->where('fingerprint', $fingerprint)->where('public_key', $public_key)->first();
	}

}
