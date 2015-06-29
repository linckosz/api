<?php

namespace bundles\lincko\api\models\data;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class ChatsComments extends Model {

	protected $connection = 'data';

	protected $table = 'chats_comments';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'chats_id',
		'users_id',
		'comment',
	);
	
////////////////////////////////////////////

	//One(ChatsComments) to Many(Chats)
	public function chats(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Chats', 'chats_id');
	}

	//One(ChatsComments) to Many(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'users_id');
	}

////////////////////////////////////////////

	public static function validComment($data){
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

}