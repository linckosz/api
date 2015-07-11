<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;

use \bundles\lincko\api\models\libs\History;

abstract class ModelLincko extends Model {

	protected $archive = array();

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

	//Do nothing if no any Many to Many relation
	//Add an underscore "_"  as prefix to avoid any conflict ($this->_tasks vs $this->tasks)
	public function addMultiDependencies(){}

	public function getCompany(){
		return '_';
	}

	public function getHistory($detail=false){
		$history = false;
		if(count($this->archive)>0 && isset($this->id)){
			$records = History::whereType($this->getTable())->whereTypeId($this->id)->get();
			foreach ($records as $key => $value) {
				if(in_array($value->attribute, $this->archive)){
					$created_at = (new \DateTime($value->created_at))->getTimestamp();
					if(!$history){ $history = new \stdClass; }
					if(!isset($history->$created_at)){ $history->$created_at = new \stdClass; }
					if(!isset($history->$created_at->{$value->id})){ $history->$created_at->{$value->id} = new \stdClass; }
					$history->$created_at->{$value->id}->created_by = $value->created_by;
					$history->$created_at->{$value->id}->attribute = $value->attribute;
					if($detail){
						$history->$created_at->{$value->id}->old = $value->old;
						$history->$created_at->{$value->id}->new = $value->new;
					}
				}
			}
		}
		return $history;
	}

	//When save, it helps to keep track of history
	public function save(array $options = []){
		$app = \Slim\Slim::getInstance();
		$dirty = $this->getDirty();
		$original = $this->getOriginal();
		foreach($dirty as $key => $value) {
			if(in_array($key, $this->archive)){
				unset($history);
				$history = new History;
				$history->created_by = $app->lincko->data['uid'];
				$history->type_id = $this->id;
				$history->type = $this->getTable();
				$history->attribute = $key;
				$history->old = $original[$key];
				$history->new = $dirty[$key];
				$history->save();
			}
		}
		parent::save($options);
	}

}