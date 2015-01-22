<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Authorization extends Model {

	protected $connection = 'api';

	protected $table = 'authorization';

	protected $primaryKey = 'public_key';

	public $timestamps = true;

	protected $visible = array();

	/////////////////////////////////////

	public static function clean($user_id=NULL){
		$app = \Slim\Slim::getInstance();
		$limit = new \DateTime();
		$limit->sub(new \DateInterval('PT'.$app->lincko->security['expired'].'S'));
		
		if($user_id){
			//Only delete own user authorization records
			return self::where('user_id', '=', $user_id)->where('updated_at', '<', $limit)->delete();
		} else {
			//Delete all users expired (force expired account to resign with Email/Password)
			return self::where('updated_at', '<', $limit)->delete();
		}
	}


}