<?php


namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\Inform;
use Illuminate\Database\Capsule\Manager as Capsule;

class Messages extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'messages';
	protected $morphClass = 'messages';

	protected $dates = array();

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

	protected static $prefix_fields = array(
		'comment' => '+comment',
	);

	protected static $hide_extra = array(
		'temp_id',
		'comment',
		'viewed_by',
	);

	protected $name_code = 200;

	protected static $archive = array(
			'created_at' => array(false, 201), //[{un}] sent a new message
	);

	protected static $history_xdiff = array('comment');

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

	protected static $db = false;

	protected static $row_number = 30; //Default number of messages per chats

	protected static $id_max = false; //Default number of messages per chats
	
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

	protected static function getDB(){
		if(!self::$db){
			$app = ModelLincko::getApp();
			self::$db = Capsule::connection($app->lincko->data['database_data']);
		}
		return self::$db;
	}

	//Give access to all, will be delete later by hierarchy
	public static function filterPivotAccessList(array $list, $all=false){
		return array();
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//Because deleted_at does not exist
	public static function find($id, $columns = ['*']){
		return parent::withTrashed()->find($id, $columns);
	}

	public function checkAccess($show_msg=true){
		$app = ModelLincko::getApp();
		$this->checkUser();
		$uid = $app->lincko->data['uid'];
		if(!is_bool($this->accessibility)){
			$this->accessibility = (bool) false; //By default, for security reason, we do not allow the access
			//Since message does not have _perm attribute, we check directly the parent
			$parent = $this->getParent();
			if($parent){
				if(isset($parent->_perm)){
					$perm = json_decode($parent->_perm);
					if(!empty($perm) && isset($perm->$uid)){
						$this->accessibility = (bool) true;
					}
				} else {
					$this->accessibility = $parent->checkAccess($show_msg);
				}
			}
			//Root directory
			else if(empty($parent_type) && empty($parent_id)){
				$this->accessibility = (bool) true;
			}
		}
		return parent::checkAccess($show_msg);
	}

	public function scopegetItems($query, $list=array(), $get=false){
		if($get){
			$result = array();
			if(isset($list['chats']) && count($list['chats'])>0){
				$result = self::getCollection($list['chats'], true);
				foreach($result as $key => $value) {
					$result[$key]->accessibility = true;
				}
			}
			return $result;
		} else {
			$app = ModelLincko::getApp();
			if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
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
			} else {
				$query = $query->whereId(-1); //We reject if no specific access
			}
			return $query;
		}
	}

	public static function setRowNumber($row_number=true){
		self::$row_number = 30; //Default
		if(is_integer($row_number)){
			self::$row_number = intval($row_number);
		}
	}

	public static function setIdMax($id_max=true){
		self::$id_max = false; //Default
		if(is_integer($id_max)){
			self::$id_max = intval($id_max);
		}
	}

	public static function getCollection($chats=array(), $hydrate=true, $fields_arr=false){
		$app = ModelLincko::getApp();
		$db = static::getDB();
		if(count($chats)<=0){
			$chats=array(-1);
		}
		$limit_id = '';
		//id_max is not included, and it's better to work with ID than created_at for performance and because we can have multiple same timestamp
		if(is_integer(self::$id_max)){
			$limit_id = 'AND `id` < '.intval(self::$id_max);
		}
		$fields = '';
		if(is_array($fields_arr) && count($fields_arr)>0){
			foreach ($fields_arr as $value) {
				$fields .= '`messages`.`'.addslashes($value).'`, ';
			}
		} else {
			$fields = '`messages`.*, ';
		}
		//http://stackoverflow.com/questions/12113699/get-top-n-records-for-each-group-of-grouped-results
		$sql = '
			SELECT
			 *
			FROM (
				SELECT
				 '.$fields.'
				 @num := IF(@parent_id = `parent_id`, @num + 1, 1) AS `row_number`,
				 @parent_id := `parent_id` AS `dummy`
				FROM (SELECT @num:=0, @parent_id:=0) as vars, `messages`
				WHERE `parent_id` IN ('.implode(",", $chats).') '.$limit_id.'
				ORDER BY `parent_id` DESC, `id` DESC
			) as `result`
			WHERE `result`.`row_number` <= '.intval(self::$row_number).'
			;
		';
		//Note: Do not add variable in select, it make wrong the result of @var
		$bindings = $db->select( $db->raw($sql) );
		if($hydrate){
			return self::hydrate($bindings, $app->lincko->data['database_data']);
		} else {
			return $bindings;
		}
		
	}

	public function checkPermissionAllow($level, $msg=false){
		$app = ModelLincko::getApp();
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

	public function toJson($detail=true, $options = 256){ //256: JSON_UNESCAPED_UNICODE
		if(!empty($this->recalled_by)){
			$this->comment = ' ';
		}
		$temp = parent::toJson($detail, $options);

		return $temp;
	}

	public function toVisible(){
		if(!empty($this->recalled_by)){
			$this->comment = ' ';
		}
		$model = parent::toVisible();
		return $model;
	}

	public function pushNotif($new=false, $history=false){
		$app = ModelLincko::getApp();

		if(!$new || !empty($this->recalled_by)){
			return false;
		}

		$parent = $this->getParent();
		$type = $parent->getTable();

		if($type!='chats'){
			return false;
		}

		$users = false;
		$pivot = new PivotUsers(array($type));
		if($this->tableExists($pivot->getTable())){
			$users = $pivot
			->where($type.'_id', $parent->id)
			->where('access', 1)
			->where('silence', 0)
			->get(array('users_id'));
		}

		if($users){
			if($this->created_by==0){
				$sender = $app->trans->getBRUT('api', 0, 11); //LinckoBot
			} else {
				$sender = Users::find($this->created_by)->getUsername();
			}
			if($parent->single){
				$title = $sender;
				$content = $this->comment;
			} else {
				$title = $parent->title;
				$content = $sender.': '.$this->comment;
			}
			$alias = array();
			foreach ($users as $value) {
				if($value->users_id != $this->created_by && $value->users_id != $app->lincko->data['uid']){
					$user = Users::find($value->users_id);
					$alias[$value->users_id] = $user->getSha();
				}
			}
			unset($alias[$app->lincko->data['uid']]); //Exclude the user itself
			if(!empty($alias)){
				$inform = new Inform($title, $content, false, $alias, $this, array(), array('email')); //Exclude email
				$inform->send();
			}
		}
		return true;
	}

}
