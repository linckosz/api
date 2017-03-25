<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class History extends Model {

	protected $connection = 'data';

	protected $table = 'history';
	protected $morphClass = 'history';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array('id');
	
////////////////////////////////////////////

	public function __construct(array $attributes = array()){
		$app = ModelLincko::getApp();
		$this->connection = $app->lincko->data['database_data'];
		parent::__construct($attributes);
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function save(array $options = array()){
		$app = \Slim\Slim::getInstance();
		if(isset($this->id)){
			//Only allow creation
			return false;
		}
		$return = parent::save($options);
		usleep(rand(30000, 35000)); //30ms
		return $return;
	}

}
