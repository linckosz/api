<?php


namespace bundles\lincko\api\models\data;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class Messages extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'messages';
	protected $morphClass = 'messages';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'created_by',
		'recalled_by',
		'comment',
	);

	// CUSTOMIZATION // 

	protected $show_field = 'comment';

	protected $search_fields = array(
		'comment',
	);

	protected $name_code = 200;

	protected $limit_history = 40;

	//We don't record any history for chats messages
	protected static $archive = array();

	protected static $parent_list = 'chats';

	protected $model_integer = array(
		'recalled_by',
	);

	protected static $allow_single = false;
	protected static $allow_role = false;

	protected static $permission_sheet = array(
		2, //[RCU] owner
		1, //[RCU] max allow || super
	);

	protected static $access_accept = false;

	protected static $has_perm = false;
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'files' => array('comments_x_files', array('access')),
	);

	//Many(comments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'created_by');
	}

	//Many(comments) to Many(Chats)
	public function chats(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Chats', 'parent_id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->comment) && !self::validTextNotEmpty($form->comment, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Give access to all, will be delete later by hierarchy
	public static function filterPivotAccessList(array $list, $all=false){
		return array();
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function scopegetItems($query, $list=array(), $get=false){
		//It will get all roles with access 1, and all roles which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) use ($list) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) use ($list) {
				if(isset($list['chats']) && count($list['chats'])>0){
					$query = $query
					->whereIn('messages.parent_id', $list['chats']);
				} else {
					$query = $query
					->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include 'projects'
				}
			});
		});
		//Also include trashed because we don't have deleted_at
		$query = $query->withTrashed();
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true;
			}
			return $result;
		} else {
			return $query;
		}
	}

	public static function toto($chats, $row_number=30){
		$sql = '
			set @num := 0, @parent_id := 0;
			select
			   `id`, `temp_id`, `created_at`, `updated_at`, `updated_by`, `recalled_by`, `noticed_by`, `viewed_by`, `comment`,
			   @num := if(@parent_id = `parent_id`, @num + 1, 1) as `row_number`,
			   @parent_id := `parent_id` as `parent_id`
			from `messages` force index(`parent_id`)
			where `parent_id` IN (4,5,7)
			having `row_number` <= :row_number
			ORDER BY `parent_id` ASC, `id` DESC
			;
		';
		$result = $db->select( $db->raw($sql), array(
			'projects_id' => $project->id,
			'chats' => $chats,
		));
		return $result;
	}

	public function checkPermissionAllow($level, $msg=false){
		$app = self::getApp();
		$this->checkUser();
		if(!$this->checkAccess()){
			return false;
		}
		$level = $this->formatLevel($level);
		if(!isset($this->id) && $level==1){ //Allow creation
			return true;
		}
		//Recall (2 minutes on updated_at)
		if(isset($this->updated_at)){
			$time_recall = time()-(int)(new \DateTime($this->updated_at))->getTimestamp();
			if($level==2 && $this->created_by==$app->lincko->data['uid'] && $time_recall<120){
				return true;
			}
		}
		return false;
	}

	public function toJson($detail=true, $options = 0){
		if(!empty($this->recalled_by)){
			$this->comment = '...';
			$temp = parent::toJson($detail, $options);
			$temp = json_decode($temp);
			$temp->new = 0;
			$temp = json_encode($temp, $options);
		} else {
			$temp = parent::toJson($detail, $options);
		}
		return $temp;
	}

	public function toVisible(){
		if(!empty($this->recalled_by)){
			$this->comment = '...';
			$model = parent::toVisible();
			$model->new = false;
		} else {
			$model = parent::toVisible();
		}
		return $model;
	}

	public function getHistoryCreation($history_detail=false, array $parameters = array(), $items=false){
		return null;
	}

	//toto, delete save, it was for test only
	public function save(array $options = array()){
		$this->accessibility = true;
		$return = parent::save($options);
		return $return;
	}

}
