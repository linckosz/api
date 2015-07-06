<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;

abstract class ModelLincko extends Model {

	//Note: In relation functions, cannot not use underscore "_", it will not work. And do not use the same name as the Model itself.

	//This function helps to get all instance related to the user itself only
	//It needs to redefine the related function user() too
	public function scopegetLinked($query){
		return $query->whereHas('users', function ($query) {
			$query->theUser();
		});
	}

	//No need to abstract it, but need to redefined for the Models that use it
	public function users(){
		return true;
	}

	//Do not if no any Many to Many relation
	//Add an underscore "_"  as prefix to avoid any conflict ($this->_tasks vs $this->tasks)
	public function addMultiDependencies(){}

	public function getCompany(){
		return null;
	}

}