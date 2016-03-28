<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;

class TranslationModel extends Model {

	protected $table = 'translation';

	public $timestamps = false;

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

	//"READ ONLY" mode
	//Overwrite the basic function save() to not update or add anymore line
	public function save(array $options = array()){ return false; }

	protected static function checkDeployment(){
		$app = \Slim\Slim::getInstance();
		if( isset($app->lincko->deployment) && password_verify($app->lincko->deployment, '$2y$10$J6gakNmqkjrpnyMFJHhyq.JQves6JslSHJLKqpWXfZVJ6qpDKDXK6') ){
			return true;
		}
		return false;
	}

	public static function queryInsert($bundle, $attributes){
		$return = false;
		if(static::checkDeployment()){
			$return = self::on($bundle)->insert((array) $attributes);
			return true;
		}
		return $return;
	}

	public function querySave(){
		$return = false;
		if(static::checkDeployment()){
			$dirty = $this->getDirty();
			if(count($dirty) > 0){
				$return = $this->where('category', $this->category)->where('phrase', $this->phrase)->getQuery()->update((array) $dirty);
			}
		}
		return $return;
	}

}
