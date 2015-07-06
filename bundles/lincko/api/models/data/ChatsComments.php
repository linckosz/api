<?php

namespace bundles\lincko\api\models\data;

use \libs\ModelLincko;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class ChatsComments extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'chats_comments';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'created_by',
		'updated_by',
		'chats_id',
		'-comment',
	);
	
////////////////////////////////////////////

	//Many(ChatsComments) to One(Chats)
	public function chats(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Chats', 'chats_id');
	}

////////////////////////////////////////////

	public static function validComment($data){
		if(empty($data)){ return true; }
		return true;
	}

	public static function isValid($form){
		$optional = true;
		return
			   $optional
			&& isset($form->comment) && self::validComment($form->comment)
			;
	}

////////////////////////////////////////////

	//We overwritte this function because it's linked to "chats", not "users" directly
	public function scopegetLinked($query){
		return $query->whereHas('chats', function ($query) {
			$query->getLinked();
		});
	}
	
}