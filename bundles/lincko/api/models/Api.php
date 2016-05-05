<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

class Api extends Model {

	protected $connection = 'api';

	protected $table = 'api';

	protected $primaryKey = 'api_key';

	public $timestamps = false;

	protected $visible = array();

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

}
