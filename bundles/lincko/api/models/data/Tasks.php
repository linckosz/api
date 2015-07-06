<?php

namespace bundles\lincko\api\models\data;

use \libs\ModelLincko;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Tasks extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'tasks';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'created_by',
		'updated_by',
		'title',
		'comment',
		'duration',
		'fixed',
		'status',
		'progress',
		'_tasks_dependency',
		'_users_incharge',
		'_users_approver',
	);
	
////////////////////////////////////////////

	//Many(Tasks) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'projects_id');
	}

	//Many(Tasks) to Many(Tasks)
	public function tasksDependency(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_tasks_dependency', 'tasks_id', 'tasks_id_dependency')->withPivot('delay');
	}

	//Many(Tasks) to Many(Users)
	public function usersIncharge(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'tasks_x_users_incharge', 'tasks_id', 'users_id');
	}

	//Many(Tasks) to Many(Users)
	public function usersApprover(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'tasks_x_users_approver', 'tasks_id', 'users_id');
	}

////////////////////////////////////////////
	public static function validTitle($title){
		return true;
	}

	//Optional
	public static function validComment($data){
		if(empty($data)){ return true; }
		return true;
	}

	public static function isValid($form){
		$optional = true;
		if($optional && isset($form->description)){ $optional = self::validDescription($form->description); }
		return
			   $optional
			&& isset($form->title) && self::validTitle($form->title)
			;
	}

////////////////////////////////////////////

	public function scopegetLinked($query){
		return $query->whereHas('projects', function ($query) {
			$query->getLinked();
		});
	}

	//Insure to place the new properties in 'visible' array
	public function addMultiDependencies(){
		unset($result);
		$result = new \stdClass;
		$data = $this->tasksDependency;
		if(!is_null($data)){
			foreach ($data as $key => $value) {
				$result->{$value->id} = new \stdClass;
				$result->{$value->id}->delay = $value->pivot->delay;
			}
		}
		$this->_tasks_dependency = $result;
		
		unset($result);
		$result = new \stdClass;
		$data = $this->usersIncharge;
		if(!is_null($data)){
			foreach ($data as $key => $value) {
				$result->{$value->id} = new \stdClass;
			}
		}
		$this->_users_incharge = $result;

		unset($result);
		$result = new \stdClass;
		$data = $this->usersApprover;
		if(!is_null($data)){
			foreach ($data as $key => $value) {
				$result->{$value->id} = new \stdClass;
			}
		}
		$this->_users_approver = $result;
	}

	public function getCompany(){
		return $this->projects->getCompany();
	}

}
