<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\Authorization;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Projects extends Model {

	protected $connection = null;

	protected $table = 'users';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'username',
	);
	
////////////////////////////////////////////

	//Construct and setup basic parameters
	public function __construct(){
		$this->connection = 'api';
		parent::__construct();
	}

	public static function toto($db){
		//$this->connection = $db;
	}

	protected function setList(){
		$app = $this->app;
		$bundle = $this->bundle;
		if(!isset($app->lincko->databases[$bundle])){
			\libs\Watch::php('The database is not registered: '.$bundle, 'Database',__FILE__,true);
			return false;
		} else if(!Capsule::schema($bundle)->hasTable('translation')){
			return false;
		}
		if(!isset($this->list[$bundle]) && Capsule::schema($bundle)->hasTable('translation')){
			$this->list[$bundle] = array();
			$list = &$this->list[$bundle]; //Pointer
			$sql = 'SHOW FULL COLUMNS FROM `translation` WHERE LOWER(`Type`)=\'text\';';
			$db = Capsule::connection($bundle);
			$data = $db->select( $db->raw($sql) );
			foreach ($data as $key => $value) {
				$keylong = $value['Field'];
				$keyshort = preg_replace("/-.*/ui", '', $keylong);
				if(!isset($list[$keylong])){
					$list[$keylong] = $value['Field'];
				}
				if(!isset($list[$keyshort])){
					$list[$keyshort] = $value['Field'];
				}
				$this->listfull[$value['Field']] = $value['Comment'];
			}
		}
		return true;
	}

}