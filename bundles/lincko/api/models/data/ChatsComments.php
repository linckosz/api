<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class ChatsComments extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'chats_comments';
	protected $morphClass = 'chats_comments';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'created_by',
		'updated_by',
		'comment',
	);

	// CUSTOMIZATION //

	protected $show_field = 'comment';

	protected $search_fields = array(
		'comment',
	);

	protected $archive = array(
		'created_at' => 201, //[{un|ucfirst}] sent a new message.
		'_' => 202,//[{un|ucfirst}] modified a message.
		'comment' => 202,//[{un|ucfirst}] modified a message.
		'_access_0' => 296, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a message.
		'_access_1' => 297, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a message.
		'_restore' => 298,//[{un|ucfirst}] restored a message.
		'_delete' => 299,//[{un|ucfirst}] deleted a message.
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'chats_id' => '\\bundles\\lincko\\api\\models\\data\\Chats',
	);

	protected static $relations_keys = array(
		'users',
		'chats',
	);

	protected $parent = 'chats';
	
////////////////////////////////////////////

	//Many(ChatsComments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'created_by');
	}

	//Many(ChatsComments) to One(Chats)
	public function chats(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Chats', 'chats_id');
	}

////////////////////////////////////////////

	public static function validComment($data){
		$return = is_string($data) && strlen(trim($data))>0;
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function isValid($form){
		if(!isset($form->comment)){ self::noValidMessage(false, 'comment'); } //Required
		return
			     isset($form->comment) && self::validComment($form->comment)
			;
	}

////////////////////////////////////////////

	//We overwritte this function because it's linked to "chats", not "users" directly
	//This will not download message owned by the user but that cannot be displayed because not in a chat folder
	public function scopegetLinked($query){
		return $query
		//->with('chats')
		->whereHas('chats', function ($query) {
			$query->getLinked();
		});
	}

	//We allow creation only
	/*
			'chats_comments' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 0 , 0 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 1 ), //outsider
				1	=> array( 1 , 0 , 0 , 1 ), //administrator
				2	=> array( 1 , 0 , 0 , 1 ), //manager
				3	=> array( 1 , 0 , 0 , 1 ), //viewer
			),
	*/
	public function checkRole($level){
		$app = self::getApp();
		$this->checkUser();
		$level = $this->formatLevel($level);
		if(isset($this->permission_allowed[$level])){
			return $this->permission_allowed[$level];
		}
		if($level<=0){ //Allow only read for all
			$this->permission_allowed[$level] = (bool) true;
			return true;
		}
		if(!isset($this->id) && $level<=1){ //Allow creation
			$this->permission_allowed[$level] = (bool) true;
			return true;
		}
		return parent::checkRole(3); //this will only launch error, since $level = 3
	}
	
}