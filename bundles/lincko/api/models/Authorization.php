<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\data\Users;
use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use Carbon\Carbon;

class Authorization extends Model {

	protected $connection = 'api';

	protected $table = 'authorization';

	protected $primaryKey = 'public_key';
	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	public $timestamps = true;

	protected $visible = array();

	/////////////////////////////////////

	public static function clean(){
		$app = ModelLincko::getApp();
		$limit = Carbon::now();
		$limit->second = $limit->second - intval($app->lincko->security['expired']);
		return self::where('updated_at', '<', $limit)->delete();
	}

	public static function find_finger($public_key, $fingerprint){
		return self::where('public_key', $public_key)->where('fingerprint', $fingerprint)->first();
	}

	public static function getPublicKey($fingerprint){
		$app = ModelLincko::getApp();
		if($authorization = self::where('sha', Users::getUser()->username_sha1)->where('fingerprint', $fingerprint)->first()){
			return $authorization->public_key;
		}
		return $app->lincko->security['public_key'];
	}

}
