<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class Comments extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'comments';
	protected $morphClass = 'comments';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'created_at',
		'created_by',
		'hidden_by',
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
		'_restore' => 298,//[{un|ucfirst}] restored a message.
		'_delete' => 299,//[{un|ucfirst}] deleted a message.
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	protected static $permission_sheet = array(
		0, //[R] owner
		2, //[RCU] grant
		1, //[RC] max allow
	);

	//Authorized by default since it's not part of a company
	protected static $permission_grant = 1;
	
////////////////////////////////////////////

	//Many(ChatsComments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'created_by');
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

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

	//Set or not inappropriate content
	public function setHiddenBy($hide=true){
		if($class = $this->getClass($this->type)){
			if($model = $class::find($this->type_id)){
				if($model->getCompanyGrant()){
							if($hide){
						$this->hidden_by = $app->lincko->data['uid'];
					} else {
						$this->hidden_by = null;
					}
					return $this->save();
				}
			}
		}
		return false;	
	}

	public function toJson($detail=true, $options = 0){
		$app = self::getApp();
		if($this->hidden_by!=null){
			$this->comment = $app->trans->getBRUT('data', 1, 3); //The content of this message was inappropriate, it has been deleted.
			$temp = parent::toJson($detail, $options);
			$temp = json_decode($temp);
			$temp->new = 0;
			$temp = json_encode($temp, $options);
		} else {
			$temp = parent::toJson($detail, $options);
		}
		return $temp;
	}
	
}