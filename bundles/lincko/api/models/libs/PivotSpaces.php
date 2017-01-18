<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Spaces;

class PivotSpaces extends Model {

	protected $connection = 'data';

	protected $table = 'spaces_x';
	protected $morphClass = 'spaces_x';

	public $timestamps = false;

////////////////////////////////////////////

	public function __construct(array $attributes = array()){
		$app = ModelLincko::getApp();
		$this->connection = $app->lincko->data['database_data'];
		parent::__construct($attributes);
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

////////////////////////////////////////////

}
