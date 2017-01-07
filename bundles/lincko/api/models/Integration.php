<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Integration extends Model {

	protected $connection = 'api';

	protected $table = 'integration';

	protected $primaryKey = 'code';
	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	public $timestamps = true;

	protected $visible = array();

	/////////////////////////////////////

	public static function clean(){
		$app = \Slim\Slim::getInstance();
		$limit = Carbon::now();
		$limit->second = $limit->second - intval($app->lincko->security['expired']);
		return self::where('updated_at', '<', $limit)->delete();
	}

}
