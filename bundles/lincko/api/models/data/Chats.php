<?php

namespace bundles\lincko\api\models\data;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Chats extends Model {

	protected $connection = 'data';

	protected $table = 'chats';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'title',
		'multi',
	);

////////////////////////////////////////////

	//One(Chats) to Many(ChatsComments)
	public function chatsComments(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\ChatsComments', 'chats_id');
	}

	//Many(Chats) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_chats', 'users_id', 'chats_id');
	}


////////////////////////////////////////////
	public static function validTitle($data){
		return preg_match("/^\S{1,104}$/u", $data);
	}

	//Optional
	public static function validMulti($data){
		if(empty($data)){ return true; }
		return preg_match("/^[01]{1}$/u", $data);
	}
	
	public static function isValid($form){
		$optional = true;
		if($optional && isset($form->multi)){ $optional = self::validMulti($form->multi); }
		return
			   $optional
			&& isset($form->title) && self::validTitle($form->title)
			;
	}

////////////////////////////////////////////

}