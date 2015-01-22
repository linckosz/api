<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Test extends Model {

	protected $connection = 'api';

	protected $table = 'test';

	protected $primaryKey = 'id';

	public $timestamps = true;
	
	use SoftDeletingTrait;
	protected $dates = ['deleted_at'];

	protected $visible = array();

}