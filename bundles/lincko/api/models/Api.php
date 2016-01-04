<?php

namespace bundles\lincko\api\models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Api extends Model {

	protected $connection = 'api';

	protected $table = 'api';

	protected $primaryKey = 'api_key';

	public $timestamps = false;

	protected $visible = array();

	/////////////////////////////////////




}