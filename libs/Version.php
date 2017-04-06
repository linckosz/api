<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;

class Version extends Model {

	protected $connection = 'default';

	protected $table = 'version';

	protected $primaryKey = 'id';

	public $timestamps = false;

	protected $visible = array();

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function save(array $options = array()){
		if(!isset($this->id) || $this->id!=1){
			//Only allow modifying one line
			return false;
		}
		$return = parent::save($options);
		usleep(rand(30000, 35000)); //30ms
		return $return;
	}

}
