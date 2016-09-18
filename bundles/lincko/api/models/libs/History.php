<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class History extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'history';
	protected $morphClass = 'history';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array();

	protected $accessibility = true; //Always allow History creation

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] max allow || super
	);

	protected static $parent_list = array('users', 'comments', 'chats', 'workspaces', 'projects', 'tasks', 'notes', 'files');
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		return true;
	}

	//We do not attach
	public function pivots_save(array $parameters = array()){
		return true;
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$columns = self::getColumns();
		//Indicate that the user aknowledge the creation notification
		if(in_array('noticed_by', $columns)){
			$noticed_by = ';'.$app->lincko->data['uid'].';';
			if(strpos($this->noticed_by, $noticed_by) === false){
				$noticed_by .= $this->noticed_by;
				$this->noticed_by = $noticed_by;
			}
		}
		if(isset($this->id)){
			//Only allow notified_by modification
			$dirty = $this->getDirty();
			if(count($dirty)!=1 || !isset($dirty['noticed_by'])){
				return false;
			}
			$this->timestamps = false; //Don't update updated_at
		}
		$return = Model::save($options);
		return $return;
	}

	public function checkAccess($show_msg=true){
		if(isset($this->id)){
			//Only allow notified_by modification
			$dirty = $this->getDirty();
			if(count($dirty)==1 && isset($dirty['noticed_by'])){
				$this->accessibility = (bool) true;
				return true;
			}
			return false;
		}
		return parent::checkAccess($show_msg);
	}

	public function checkPermissionAllow($level, $msg=false){
		$this->checkUser();
		$level = $this->formatLevel($level);
		if($level==1){ //Creation
			return true;
		} else if($level==2){ //Edition (noticed_by only)
			$dirty = $this->getDirty();
			if(count($dirty)==1 && isset($dirty['noticed_by'])){
				return true;
			}
			return false;
		}
		return false;
	}

	public static function historyNoticed($list){
		$app = self::getApp();
		$partial = false;
		$uid = $app->lincko->data['uid'];
		if(count($list)>0){
			$histories = History::where('noticed_by', 'NOT LIKE', '%;'.$app->lincko->data['uid'].';%')
			->where(function ($query) use ($list) {
				foreach ($list as $type => $list_id) {
					foreach ($list_id as $id => $timestamp) {
						$query = $query
						->orWhere(function ($query) use ($type, $id, $timestamp) {
							$query
							->whereParentType($type)
							->whereParentId($id);
							if($timestamp!==true){
								$created_at = (new \DateTime('@'.$timestamp))->format('Y-m-d H:i:s');
								$query = $query->where('created_at', '<=', $created_at);
							}
						});
					}
				}
			})->get();
			$parent_list = array();
			//Update History
			foreach ($histories as $history) {
				$history->save();
				$parent_list[$history->parent_type][$history->parent_id] = true;
			}
			//Update object itself
			foreach ($list as $type => $list_id) {
				$class = Users::getClass($type);
				if($class){
					foreach ($list_id as $id => $timestamp) {
						if($model = $class::withTrashed()->find($id)){
							if(strpos($model->noticed_by, ';'.$app->lincko->data['uid'].';') === false){
								$noticed_by = $model->noticed_by.';'.$app->lincko->data['uid'].';';
								$class::where('id', $id)->getQuery()->update(['noticed_by' => $noticed_by]); //toto => with about 200+ viewed, it crashes (1317 Query execution was interrupted)
								$model->touchUpdateAt();
								if(!$partial){ $partial = new \stdClass; }
								if(!isset($partial->$uid)){ $partial->$uid = new \stdClass; }
								if(!isset($partial->$uid->$type)){ $partial->$uid->$type = new \stdClass; }
								if(!isset($partial->$uid->$type->$id)){ $partial->$uid->$type->$id = new \stdClass; }
							} else if(isset($parent_list[$type]) && isset($parent_list[$type][$id])){
								$model->touchUpdateAt();
								if(!$partial){ $partial = new \stdClass; }
								if(!isset($partial->$uid)){ $partial->$uid = new \stdClass; }
								if(!isset($partial->$uid->$type)){ $partial->$uid->$type = new \stdClass; }
								if(!isset($partial->$uid->$type->$id)){ $partial->$uid->$type->$id = new \stdClass; }
							}
						}
					}
				}
			}
		}
		return $partial;
	}

}
