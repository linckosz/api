<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model {

	protected $connection = 'api';

	protected $table = 'integration';

	public $incrementing = false; //This helps to get primary key as a string instead of an integer

	public $timestamps = true;

	protected $visible = array();

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public static function find($id, $columns = ['*']){
		return false;
	}

	public static function check($data){
		\libs\Watch::php($data, '$Integration', __FILE__, __LINE__, false, false, true);
	}

}
