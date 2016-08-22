<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;

class Session extends Model {

	protected $connection = 'sessions';

	protected $table = 'sessions';

	public $timestamps = false;

	protected $fillable = array('*');

}
