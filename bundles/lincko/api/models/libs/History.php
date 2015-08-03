<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class History extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'history';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array();

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);
	
////////////////////////////////////////////

}
