<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Chats extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'chats';
	protected $morphClass = 'chats';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'title',
		'multi',
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
	);

	protected $archive = array(
		'created_at' => 101, //[{un|ucfirst}] created a new chat group.
		'_' => 102,//[{un|ucfirst}] modified a chat group.
		'title' => 103,//[{un|ucfirst}] changed a chat group title.
		'_access_0' => 196, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a chat group.
		'_access_1' => 197, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a chat group.
		'_restore' => 198,//[{un|ucfirst}] restored a chat group.
		'_delete' => 199,//[{un|ucfirst}] deleted a chat group.
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	protected static $relations_keys = array(
		'users'
	);

	protected $parent = 'companies';

////////////////////////////////////////////

	//Many(Chats) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_chats', 'chats_id', 'users_id')->withPivot('access');
	}

	//One(Chats) to Many(ChatsComments)
	public function chatsComments(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\ChatsComments', 'chats_id');
	}


////////////////////////////////////////////
	public static function validTitle($data){
		$return = preg_match("/^.{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validMulti($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^[01]{1}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}
	
	public static function isValid($form){
		if(!isset($form->title)){ self::noValidMessage(false, 'title'); } //Required
		return
			     isset($form->title) && self::validTitle($form->title)
			&& (!isset($form->multi) || self::validMulti($form->multi)) //Optional
			;
	}

////////////////////////////////////////////

	public function scopegetLinked($query){
		return $query
		//->with('users')
		->whereHas('users', function ($query) {
			$app = self::getApp();
			$query->where('users_id', $app->lincko->data['uid'])->where('access', 1);
		});
	}

	//We allow creation, and editing for the creator only
	public function checkRole($level){
		$app = self::getApp();
		$level = $this->formatLevel($level);
		if(isset($this->id) && $level<=1 && $this->created_by==$app->lincko->data['uid']){ //Allow editing for creator only
			return true;
		} else if(isset($this->id) && $level<=0){ //Allow only read for others
			return true;
		} else if(!isset($this->id) && $level<=1){ //Allow creation
			return true;
		} else {
			$level = 3; //force to error, disable the deletion
		}
		return parent::checkRole($level); //this will only launch error, since $level = 3
	}

}