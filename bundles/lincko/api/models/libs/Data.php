<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\chatsComments;

class Data {

	protected $app = NULL;
	protected $data = NULL;
	protected $models = array();
	protected $lastvisit = 0; //Format 'Y-m-d H:i:s'

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		$this->setLastVisit();
		return true;
	}

	protected function setLastVisit(){
		if(isset($this->data->data->lastvisit)){
			if(is_int($this->data->data->lastvisit) && $this->data->data->lastvisit>=0){
				$this->lastvisit = (new \DateTime('@' . $this->data->data->lastvisit))->format('Y-m-d H:i:s');
			}
		}
	}

	protected function getModels(){
		$sql = 'SHOW TABLES;';
		$db = Capsule::connection('data');
		$data = $db->select( $db->raw($sql) );

		$classes = array();
		foreach ($data as $key => $value) {
			$tp = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC(array_values($value)[0]);
			if(class_exists($tp)){
				$classes[] = $tp;
			}
		}
		$this->models = $classes;
	}

	protected function getList($detail=false){
		$app = $this->app;
		$result = new \stdClass;
		$this->getModels();

//////test
		//$this->lastvisit = 0;
//////test

		foreach($this->models as $key => $value) {
			if($this->lastvisit != 0){
				$data = $value::getLinked()->where('updated_at', '>=', $this->lastvisit)->get();
			} else {
				$data = $value::getLinked()->get();
			}
			//Check if there is at least one update
			if(!$data->isEmpty()){
				//Add multi ID dependencies (Many to Many)
				foreach ($data as $key => $value) {
					$data[$key]->addMultiDependencies();
				}
				//Get table name
				$table_name = (new $value)->getTable();
				//If the table need to be shown as viewed, if it doesn't exist we consider it's already viewed
				$table = array();
				$table_tp= json_decode($data->toJson());
				foreach ($data as $key => $value) {
					$table_tp[$key]->new = 0;
					if(isset($value->viewed_by)){
						if(strpos($value->viewed_by, '-'.$app->lincko->data['uid'].'-') === false){
							$table_tp[$key]->new = 1;
						}
					}
					$uid = $app->lincko->data['uid'];
					$compid = $data[$key]->getCompany();
					$id = $table_tp[$key]->id;
					if($detail){
						$temp = $table_tp[$key];
						//Delete ID property since it becomes the key of the table
						unset($temp->{'id'});
					} else {
						$temp = new \stdClass;
					}

					//Use Timestamp for JS
					if(isset($temp->created_at)){  $temp->created_at = (new \DateTime($temp->created_at))->getTimestamp(); }
					if(isset($temp->updated_at)){  $temp->updated_at = (new \DateTime($temp->updated_at))->getTimestamp(); }
					if(isset($temp->deleted_at)){  $temp->deleted_at = (new \DateTime($temp->deleted_at))->getTimestamp(); }

					if(!isset($result->$uid)){
						$result->$uid = new \stdClass;
					}
					if(is_null($compid)){ $compid = '_'; }
					if(!isset($result->$uid->$compid)){
						$result->$uid->$compid = new \stdClass;
					}
					if(!isset($result->$uid->$compid->$table_name)){
						$result->$uid->$compid->$table_name = new \stdClass;
					}
					//Create object
					$result->$uid->$compid->$table_name->{$id} = $temp;
				}
				unset($data);
			}
		}
		return $result;
	}

	public function getLatest(){
		return $this->getList(true);
	}

	public function getSchema(){
		return $this->getList(false);
	}

}

