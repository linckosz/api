<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model {

	protected $connection = 'api';

	protected $table = 'test';

	protected $primaryKey = 'id';

	public $timestamps = true;
	
	use SoftDeletes;
	protected $dates = ['deleted_at'];

	protected $visible = array();

}