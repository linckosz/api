<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\Updates;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\Inform;
use Carbon\Carbon;
use \libs\Json;
use \libs\Folders; 
use \libs\Video;
use \libs\IptcManager;
use \libs\SimpleImage;
use \libs\Datassl;
use \libs\Email;
use WideImage\WideImage;

class Files extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'files';
	protected $morphClass = 'files';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'updated_at',
		'deleted_at',
		'created_by',
		'updated_by',
		'fav',
		'version_of',
		'name',
		'category',
		'comment',
		'ori_type',
		'ori_ext',
		'thu_type',
		'thu_ext',
		'size',
		'width',
		'height',
		'orientation',
		'progress',
		'error',
		'sha',
		'search',
		'_parent',
		'_tasks',
		'_notes',
		'_comments',
		'_spaces',
		'_perm',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected static $prefix_fields = array(
		'name' => '+name',
		'comment' => '-comment',
	);

	protected static $hide_extra = array(
		'temp_id',
		'name',
		'category',
		'comment',
		'search',
		'ori_type',
		'ori_ext',
		'thu_type',
		'thu_ext',
		'viewed_by',
	);

	protected $name_code = 900;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => array(true, 901), //[{un}] uploaded a file
		'_' => array(true, 902), //[{un}] modified a file
		'name' => array(true, 903), //[{un}] changed a file name
		'comment' => array(true, 904), //[{un}] modified a file description
		'created_voice' => array(true, 951), //[{un}] sent a voice message
		'pivot_users_access_0' => array(true, 996), //[{un}] blocked [{cun}]'s access to a file
		'pivot_users_access_1' => array(true, 997), //[{un}] authorized [{cun}]'s access to a file
		'_restore' => array(true, 998), //[{un}] restored a file
		'_delete' => array(true, 999), //[{un}] deleted a file
	);

	protected static $history_xdiff = array('comment');

	/*
		IMPORTANT:
		'users', 'chats', 'projects' are hardly attached
		'tasks', 'notes', 'comments' are softly attached, meaning that the file will be attached to the parent project but will be given the dependency to the tasks or note
	*/
	protected static $parent_list = array('workspaces', 'users', 'chats', 'projects');
	protected static $parent_list_soft = array('tasks', 'notes', 'comments'); //toto => adding 'comments' makes it crash, they disapear

	protected $model_integer = array(
		'fav',
		'version_of',
		'size',
		'width',
		'height',
		'orientation',
		'progress',
	);

	protected $model_boolean = array(
		'error',
	);

	protected static $allow_single = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);

	protected static $access_accept = false;

	protected static $has_perm = true;
	
////////////////////////////////////////////

	protected $imagequalitysize = '1920'; //1GB => 3,500 pictures
	protected $imagequalitycomp = '80';
	protected $imagecompressed = false;
	protected $realorientation = true;

	protected $videoquality = 1; //[0]480p / [1]720p / [2]1080p

	//public $category = 'file'; //Store in file by default
	protected static $list_categories = array(
		//Note: because compilation of ffmpeg fails with webm support, we considerate it as a file

		'image' => array('image/bmp', 'image/x-windows-bmp', 'image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/vnd.wap.wbmp'),

		'video' => array('application/asx', 'application/vnd.ms-asf', 'application/vnd.rn-realmedia', 'application/vnd.rn-realmedia-vbr', 'application/x-mplayer2', 'application/x-pn-mpg', 'application/x-troff-msvideo', 'content/unknown', 'image/mov', 'image/mpg', 'video/3gpp', 'video/avi', 'video/dvd', 'video/mp4', 'video/mp4v-es', 'video/mpeg', 'video/mpeg2', 'video/mpg', 'video/msvideo', 'video/quicktime', 'video/xmpg2', 'video/x-flv', 'flv-application/octet-stream', 'video/x-m4v', 'video/x-matroska', 'video/x-mpeg', 'video/x-mpeg2a', 'video/x-mpg', 'video/x-msvideo', 'video/x-ms-asf', 'video/x-ms-asf-plugin', 'video/x-ms-wm', 'video/x-ms-wmv', 'video/x-ms-wmx', 'video/x-quicktime', /*'video/webm',*/ 'video/x-sgi-movie'),

		'audio' => array('audio/3gpp', 'audio/aiff', 'audio/x-aiff', 'audio/asf', 'audio/avi', 'audio/mp3', 'audio/mp4', 'audio/mpeg', 'audio/vnd.rn-realaudio', 'audio/midi', 'audio/x-midi', 'audio/mpeg3', 'audio/x-mpeg3', 'audio/mpeg', 'audio/x-mpeg', 'audio/x-pm-realaudio-plugin', 'audio/x-pn-realaudio', 'audio/x-realaudio', 'audio/wav', 'audio/x-wav', /*'audio/webm',*/ 'audio/ogg'),
	);

////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_files', array('fav')),
		'tasks' => array('tasks_x_files', array('fav')),
		'notes' => array('notes_x_files', array('fav')),
		'spaces' => array('spaces_x', array('created_at')),
		'comments' => array('comments_x_files', array('access')),
	);

	//Many(Files) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_files', 'files_id', 'users_id')->withPivot('access', 'fav');
	}

	//Many(Files) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_files', 'files_id', 'tasks_id')->withPivot('access', 'fav');
	}

	//Many(Files) to Many(Notes)
	public function notes(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'notes_x_files', 'files_id', 'notes_id')->withPivot('access', 'fav');
	}

	//One(Files) to Many(Comments)
	public function comments(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'files', 'id', 'parent_id');
	}

	//Many(Files) to Many(Comments)
	public function Depcomments(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'comments_x_files', 'files_id', 'comments_id')->withPivot('access');
	}

	//Many(Files) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'files', 'id', 'parent_id');
	}

	//Many(Files) to Many(Projects)
	public function chats(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'files', 'id', 'parent_id');
	}

	//One(Files) to Many(Users)
	public function profile(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Users', 'profile_pic');
	}

	//Many(Files) to Many(Spaces)
	public function spaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'spaces_x', 'parent_id', 'spaces_id')->where('spaces_x.parent_type', 'files')->withPivot('access', 'fav', 'created_at', 'exit_at');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_type) && !self::validType($form->parent_type, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->name) && !self::validTitle($form->name, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	protected function setPivotExtra($type, $column, $value){
		$pivot_array = array(
			$column => $value,
		);
		if($type=='spaces'){
			$pivot_array['parent_type'] = 'files';
			$pivot_array['created_at'] = $this->freshTimestamp();
			if($column=='access'){
				if($value){
					$pivot_array['exit_at'] = null;
				} else {
					$pivot_array['exit_at'] = $pivot_array['created_at'];
				}
			}
		}
		return $pivot_array;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		//It will get all roles with access 1, and all roles which are not in the relation table, but the second has to be in conjonction with projects
		unset($list['users']); //Excluse user profile picture (this record also old useless pictures), but we will have to add them later manually on Data.php
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			$query = $query
			->where(function ($query) use ($list) {
				$ask = false;
				foreach ($list as $table_name => $list_id) {
					if(in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
						if($table_name=='users'){ //unused
							$query = $query
							->orWhereHas('profile', function ($query) use ($list){
								$query = $query
								->whereIn('users.id', $list['users']);
							});
						} else {
							$this->var['parent_type'] = $table_name;
							$this->var['parent_id_array'] = $list_id;
							$query = $query
							->orWhere(function ($query) {
								$query
								->where('files.parent_type', $this->var['parent_type'])
								->whereIn('files.parent_id', $this->var['parent_id_array']);
							});
							$ask = true;
						}
					}
				}
				if(!$ask){
					$query = $query
					->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include any category
				}
			})
			->whereHas('users', function($query) {
				$app = ModelLincko::getApp();
				$query
				->where('users_id', $app->lincko->data['uid'])
				->where('access', 0);
			}, '<', 1);
			if(self::$with_trash_global){
				$query = $query->withTrashed();
			}
		} else {
			$query = $query->whereId(-1); //We reject if no specific access
		}
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

	public function scopegetProfilePics($query, $users=array(), $get=false){
		$query = $query
		->whereHas('profile', function ($query) use ($users){
			$query = $query
			->whereIn('users.id', $users);
		});
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
	
	public function checkPermissionAllow($level, $msg=false){ //toto (buggy for root chats)
		$app = ModelLincko::getApp();
		if($this->parent_type=='users' && $this->parent_id==$app->lincko->data['uid']){
			return true; //For profile picture
		}
		return parent::checkPermissionAllow($level, $msg);
	}

	

	public function toJson($detail=true, $options = 256){ //256: JSON_UNESCAPED_UNICODE
		$this->sha = base64_encode(Datassl::encrypt_smp($this->link));
		return parent::toJson($detail, $options);
	}

	public function toVisible(){
		$this->sha = base64_encode(Datassl::encrypt_smp($this->link));
		return parent::toVisible();
	}

	public function setCompression($value = true){
		$this->imagecompressed = $value;
	}

	public function setRealOrientation($value = true){
		$this->realorientation = $value;
	}

	public function setOrientation(){
		$orientation = 1;
		$flip_x = false;
		$flip_y = false;
		$angle = false;
		if(isset($this->tmp_name)){
			if($exif = @exif_read_data($this->tmp_name)){
				if(isset($exif['Orientation'])){
					$orientation = $exif['Orientation'];
					switch ($orientation) {
						case 1:
							// 1 => Do nothing
							break;
						case 2:
							// 2 => Flip horizontal (x)
							$flip_x = true;
							break;
						case 3:
							// 3 => Rotate 180 clockwise (180)
							$angle = 180;
							break;
						case 4:
							// 4 => vertical flip (y)
							$flip_y = true;
							break;
						case 5:
							// 5 => Rotate 90 clockwise and flip vertically (90 + y)
							$flip_y = true;
							$angle = 90;
							break;
						case 6:
							// 6 => Rotate 90 clockwise
							$angle = 90;
							break;
						case 7:
							// 7 => Rotate 90 clockwise and flip horizontally (90 + x)
							$flip_x = true;
							$angle = 90;
							break;
						case 8:
							// 8 => Rotate 270 clockwise (270)
							$angle = 270;
							break;
						default:
							// 1 => Do nothing
							$orientation = 1; //Force no orientation
					}
				}
			}
		}
		$this->orientation = $orientation;
		return array($flip_x, $flip_y, $angle);
	}

	public function pushNotif($new=false, $history=false){
		$app = ModelLincko::getApp();

		if(!$new){
			return false;
		}
		if($this->updated_by==0){
			return false;
		}

		$parent = $this->getParent();
		$type = $parent->getTable();

		if($type!='projects' && $type!='chats'){
			return false;
		}

		$users = false;
		$users_accept = array();
		$pivot = new PivotUsers(array($type));
		if($this->tableExists($pivot->getTable())){
			$users = $pivot
			->where($type.'_id', $parent->id)
			->where('access', 1)
			->where('silence', 0)
			->get(array('users_id'));
			foreach ($users as $value) {
				$users_accept[$value->users_id] = $value->users_id;
			}
		}

		if(isset($history->pivot_type) && $history->pivot_type=='users'){
			$users_accept[$history->pivot_id] = $history->pivot_id;
		}
		if(isset($history->parent_type) && $history->parent_type=='users'){
			$users_accept[$history->parent_id] = $history->parent_id;
		}

		if($users){
			if($this->updated_by==0){
				$sender = $app->trans->getBRUT('api', 0, 11); //LinckoBot
			} else {
				$sender = Users::find($this->updated_by)->getUsername();
			}
			$param = array('un' => $sender);
			if($history && isset($history->parameters)){
				if($json = json_decode($history->parameters)){
					foreach ($json as $key => $value) {
						$param[$key] = $value;
					}
				}
			}
			if($type=='chats' && isset($parent->single) && $parent->single){
				$title = $sender;
			} else {
				$title = $parent->title;
			}
			foreach ($users as $value) {
				if($value->users_id != $this->updated_by && $value->users_id != $app->lincko->data['uid']){
					$user = Users::find($value->users_id);
					$alias = array($value->users_id => $user->getSha());
					unset($alias[$app->lincko->data['uid']]); //Exclude the user itself
					if(empty($alias)){
						continue;
					}
					$language = $user->getLanguage();
					$content = $this->name;
					if($this->category=='voice'){
						$content = $app->trans->getBRUT('data', 1, 951, array(), $language); //[{un}] sent a voice message
						foreach ($param as $search => $replace) {
							$content = str_replace('[{'.$search.'}]', $replace, $content);
						}
					} else if($type!='chats' || !isset($parent->single) || !$parent->single){
						$content = $app->trans->getBRUT('data', 1, 901, array(), $language); //[{un}] uploaded a file
						foreach ($param as $search => $replace) {
							$content = str_replace('[{'.$search.'}]', $replace, $content);
						}
						$content .= ":\n ".$this->name;
					}
					if($this->category=='voice' || $parent->getTable()=='chats'){
						$inform = new Inform($title, $content, false, $alias, $parent, array(), array('email')); //Exclude email
					} else {
						$inform = new Inform($title, $content, false, $alias, $this, array(), array('email')); //Exclude email
					}
					$inform->send();
				}
			}
		}
		return true;
	}

	public function getHistoryCreationCode(&$items=false){
		if($this->category=='voice'){
			return static::$archive['created_voice'][1];
		}
		return parent::getHistoryCreationCode($items);
	}
	
	public function save(array $options = array()){
		$app = ModelLincko::getApp();
		$new = false;
		if(!$this->id){ //Only copy a file for new items
			if($this->category!='voice'){
				if($this->error!=0 || !$this->fileformat()){
					return false;
				}
			}
			if($this->size > 1000000000){
				$msg = $app->trans->getBRUT('api', 3, 7); //File too large
				$json = new Json($msg, true, 400);
				$json->render(400);
				return false;
			}
			try {
				Workspaces::getSFTP();
				$this->server_path = $app->lincko->filePath;
				$server_path_full = $app->lincko->filePathPrefix.$app->lincko->filePath;
				$this->setCategory();
				$this->link = md5(uniqid());
				$folder_ori = new Folders;
				$folder_ori->createPath($server_path_full.'/'.$app->lincko->data['uid'].'/');
				$this->thu_type = null;
				$this->thu_ext = null;
				$this->progress = 100;
				$this->puid = $app->lincko->data['uid'];
				$source = $this->tmp_name;
				if($this->category=='image'){
					$orientation = $this->setOrientation();
					$src = WideImage::load($this->tmp_name);
					$this->width = $src->getWidth();
					$this->height = $src->getHeight();
					$modify = false;
					$resize = false;
					$compression = 90;
					if($this->ori_type == 'image/jpeg' || $this->ori_type == 'image/png') {
						if($this->ori_type == 'image/jpeg'){
							exec("identify -format '%Q' \"$source\" 2>&1 ", $tablo, $error);
							if(!$error && is_numeric($tablo[0]) && $tablo[0]>0){
								$compression = $tablo[0];
							}
							if($this->imagecompressed){
								if($compression > $this->imagequalitycomp){
									$modify = true;
									$compression = $this->imagequalitycomp;
								}
								if($this->width > $this->imagequalitysize || $this->height > $this->imagequalitysize){
									$modify = true;
									$src = $src->resize($this->imagequalitysize, $this->imagequalitysize, 'inside', 'any');
								}
							}
						}
						if($this->orientation!=1){
							$modify = true;
							if($this->realorientation){
							//For a jpeg we check if there is any orientation, if yes we rotate and overwrite
								if($orientation[0]){ $src = $src->mirror(); } //Mirror left/right
								if($orientation[1]){ $src = $src->flip(); } //Flip up/down
								if($orientation[2]){ $src = $src->rotate($orientation[2]); }
							} //Rotation
							$this->orientation = 1;
						}

						$this->width = $src->getWidth();
						$this->height = $src->getHeight();
					}

					if($modify){
						if($this->ori_type == 'image/png'){
							$src = $src->saveToFile($folder_ori->getPath().$this->link.'.png');
							rename($folder_ori->getPath().$this->link.'.png', $folder_ori->getPath().$this->link);
						} else {
							$src = $src->saveToFile($folder_ori->getPath().$this->link.'.jpg', $compression);
							rename($folder_ori->getPath().$this->link.'.jpg', $folder_ori->getPath().$this->link);
						}
						$this->size = filesize($folder_ori->getPath().$this->link);
					} else {
						copy($this->tmp_name, $folder_ori->getPath().$this->link);
					}

					//toto:temp for support message;
					if($this->parent_type == 'chats'){
						$chat = Chats::find($this->parent_id);
						if($chat->style == 1 || $chat->style == 2){
							$mail = new Email();
							$mail->addAddress($app->lincko->email->Support);
							$subject = 'Feedback by ';
							if($chat->style == 2){
								$subject = 'Message from ';
							}
							$mail->setSubject($subject . Users::getUser()->getUsername() . ' ['.$app->lincko->data['uid'].']');
							$mail->AddAttachment($folder_ori->getPath().$this->link, 'attachment.jpg');
							$mail->sendLater('attachment');
						}	
					}

					$folder_thu = new Folders;
					
					$folder_thu->createPath($server_path_full.'/'.$app->lincko->data['uid'].'/thumbnail/');
					try {
						$src = WideImage::load($this->tmp_name);
						$src = $src->resize(256, 256, 'inside', 'any');
						if($this->realorientation){
							if($orientation[0]){ $src = $src->mirror(); } //Mirror left/right
							if($orientation[1]){ $src = $src->flip(); } //Flip up/down
							if($orientation[2]){ $src = $src->rotate($orientation[2]); }
						} //Rotation

						$has_transparency = false;
						//For PNG, check if we have any transparent pixel, if yes we do keep PNG format;
						if($this->ori_type == 'image/png'){

							$im = $src->getHandle();
							$width = $src->getWidth();
							$height = $src->getHeight();
							for($x = 0; $x < $width; $x++){
								for($y = 0; $y < $height; $y++) {
									$alpha = (imagecolorat($im,$x,$y) & 0x7F000000) >> 24;

									if($alpha > 0){
										$has_transparency = true;
										break 2;
									}
								}
							}
						}

						if($this->ori_type == 'image/png' && $has_transparency){
							$this->thu_type = 'image/png';
							$this->thu_ext = 'png';
							$src = $src->saveToFile($folder_thu->getPath().$this->link.'.png');
							rename($folder_thu->getPath().$this->link.'.png', $folder_thu->getPath().$this->link);
						} else {
							$this->thu_type = 'image/jpeg';
							$this->thu_ext = 'jpg';
							$src = $src->saveToFile($folder_thu->getPath().$this->link.'.jpg', 60);
							rename($folder_thu->getPath().$this->link.'.jpg', $folder_thu->getPath().$this->link);
						}

						/*
						//We convert PNG into JPEG, the size will be smaller for thumbnail only
						$this->thu_type = 'image/jpeg';
						$this->thu_ext = 'jpg';
						$src = $src->saveToFile($folder_thu->getPath().$this->link.'.jpg', 60);
						rename($folder_thu->getPath().$this->link.'.jpg', $folder_thu->getPath().$this->link);
						*/
					} catch(\Exception $e){
						\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, __LINE__, true);
						$this->thu_type = 'image/png';
						$this->thu_ext = 'png';
						copy($app->lincko->path.'/bundles/lincko/api/public/images/generic/unavailable.png', $folder_thu->getPath().$this->link);
					}
				} else if($this->category=='video'){
					$this->progress = 0; //Only video needs significant time for compression
					$this->size = 0;
					$folder_thu = new Folders;
					$folder_thu->createPath($server_path_full.'/'.$app->lincko->data['uid'].'/thumbnail/');
					$folder_txt = new Folders;
					$folder_txt->createPath($app->lincko->filePathLocal.'/'.$app->lincko->data['uid'].'/convert/'); //Because of exec limitation (does not work with ssh2.sftp), we use local link
					$this->thu_type = 'image/jpeg';
					$this->thu_ext = 'jpg';
					if($dot = strrpos($this->name, '.')){
						$this->name = substr($this->name, 0, $dot).'.mp4';
					}
					$this->ori_type = 'video/mp4';
					$this->ori_ext = 'mp4';

					$video = new Video($this->tmp_name, $app->lincko->filePath.'/'.$app->lincko->data['uid'], $this->link, $folder_txt->getPath().$this->link, Workspaces::getPrefixSFTP());
					
					if($video->thumbnail()!==0){
						return false;
					}
					if($video->convert(2)!==0){
						return false;
					}
					$info = $video->getInfo();
					$this->width = $info['width_new'];
					$this->height = $info['height_new'];
				} else if($this->category=='voice'){
					$this->ori_type = 'audio/mp3';
					$this->ori_ext = 'mp3';
					$folder_voice = new Folders;
					$folder_voice->createPath($server_path_full.'/'.$app->lincko->data['uid'].'/voice/');
					file_put_contents($folder_voice->getPath().$this->link, $source);
					$this->size = filesize($folder_voice->getPath().$this->link);
					//Calculate the duration
					$this->comment = ''.round(8*$this->size/48000);
				} else {
					copy($this->tmp_name, $folder_ori->getPath().$this->link);
					//No thumbnail for other kind of files
				}
				$new = true;
			} catch(\Exception $e){
				\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, __LINE__, true);
				return false;
			}
		}
		$dirty = $this->getDirty();
		$return = parent::save($options);

		if(
			   isset($dirty['parent_type'])
			&& isset($dirty['parent_id'])
			&& $dirty['parent_type'] == 'users'
			&& $dirty['parent_id'] == $app->lincko->data['uid']
		){ //Change profile picture
			$user = Users::getUser();
			$user->profile_pic = $this->id;
			$user->save();
		}
		
		if(
			   isset($dirty['parent_type'])
			&& isset($dirty['parent_id'])
			&& $dirty['parent_type'] == 'workspaces'
			&& $dirty['parent_id'] == $app->lincko->data['workspace_id']
		){ //Change profile picture
			if($workspace = Workspaces::getWorkspace()){
				$workspace->cus_logo = $this->id;
				$workspace->save();
			}
		}

		if($new && $this->category=='video'){
			sleep(1); //wait 1s to make sure the conversion is starting
			$this->checkProgress();
		}
		
		return $return;
	}

	public function setCategory(){
		$this->ori_type = strtolower($this->ori_type);
		foreach (static::$list_categories as $category => $list) {
			if(in_array($this->ori_type, $list)){
				$this->category = $category;
				break;
			}
		}
		return $this->category;
	}

	public function getCategory(){
		return $this->category;
	}

	public function checkProgress(){
		if($this->category=='video' && $this->progress<100 && !$this->error && isset($this->id)){
			$app = ModelLincko::getApp();
			
			$puid = $this->created_by;
			if(!is_null($this->puid)){
				$puid = $this->puid;
			}
			$path = $app->lincko->filePathLocal.'/'.$puid.'/convert/'.$this->link;
			
			if(is_file($path) && time()-filemtime($path) < 60 && $this->progress>=1){ //If the conversion file is less than 1 minutes, we should be in middle of conversion
				return true;
			}
			$url = $app->environment['slim.url_scheme'].'://'.$app->request->headers->Host.'/file/progress/'.$this->id;
			$data = json_encode(array(
				'remote' => $app->lincko->data['remote'],
				'workspace_id' => $app->lincko->data['workspace_id'],
				'uid' => $this->created_by,
				'method' => 'POST',
			));
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1); //Cannot use MS, it will crash the request
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json; charset=UTF-8',
					'Content-Length: ' . mb_strlen($data),
				)
			);
			curl_exec($ch);
			@curl_close($ch);
		}
		return true;
	}

	public function setProgress(){
		if($this->category == 'video' && $this->progress < 100 && !$this->error){
			$app = ModelLincko::getApp();
			Workspaces::getSFTP();
			set_time_limit(24*3600); //Set to 1 day workload at the most
			$puid = $this->created_by;
			if(!is_null($this->puid)){
				$puid = $this->puid;
			}
			$path = $app->lincko->filePathLocal.'/'.$puid.'/convert/'.$this->link;
			$file = $app->lincko->filePathPrefix.$this->server_path.'/'.$puid.'/'.$this->link;
			$users_tables = array();
			$users_tables[$this->created_by] = array();
			$users_tables[$this->created_by]['files'] = true;
			$loop = true;
			$progress = 100;
			$try = 10; //5s of try
			$time = $this->freshTimestamp();
			$this::where('id', $this->id)->getQuery()->update(['progress' => 1, 'updated_at' => $time, 'extra' => null]);
			usleep(rand(30000, 35000)); //30ms
			Updates::informUsers($users_tables);
			usleep(500000); //500ms
			while($loop){
				$handle = fopen($path, 'r');
				if($handle){
					if(is_file($path) && filesize($path)>0){
						$contents = fread($handle, filesize($path));
						$reg_duration = "/\b.*?Duration:\s*?(\d\d):(\d\d):(\d\d)\.(\d\d).*\b/i";
						if(preg_match_all($reg_duration, $contents, $matches, PREG_SET_ORDER)){
							$match = $matches[count($matches)-1];
							$duration = $match[1]*360000 + $match[2]*6000 + $match[3]*100 + $match[4];
							$reg_time  = "/ time=\s*?(\d\d):(\d\d):(\d\d)\.(\d\d) /i";
							if(preg_match_all($reg_time, $contents, $matches, PREG_SET_ORDER)){
								$match = $matches[count($matches)-1];
								$time = $match[1]*360000 + $match[2]*6000 + $match[3]*100 + $match[4];
								$reg_size  = "/\b.*?\d Lsize=.*\b/i";
								if($time == 0 || $duration == 0){
									$progress = 0;
								} else if($time>=$duration || preg_match_all($reg_size, $contents)){
									$progress = 100;
								} else {
									$progress = round(100*$time/$duration);
									if($progress<0){ $progress = 0; }
									else if($progress>100){ $progress = 100; }
								}
							}
							if(!is_file($file) || filesize($file)<=0){
								$try--;
								$progress = 0;
							} else if(filemtime($path) < time()-3600){ //If the conversion log is more than one hour without modification, we considerate it as fail
								$progress = 100;
								$try = 0;
							}
						}
					}
				}
				fclose($handle);
				$size = 0;
				if(is_file($file)){
					$size = (int) filesize($file);
				}
				if($progress<1){
					$progress = 1; //1% helps to show we are in middle of compression
				} else if($progress>100){
					$progress = 100;
				}
				
				if($progress>=100 || $try<=0){ 
					$loop = false;
					$temp = json_decode($this->_perm);
					if(is_object($temp)){
						foreach ($temp as $key => $value) {
							$users_tables[$key]['files'] = true;
						}
					}
				}

				$time = $this->freshTimestamp();
				$this::where('id', $this->id)->getQuery()->update(['progress' => $progress, 'size' => $size, 'updated_at' => $time, 'extra' => null]);
				usleep(rand(30000, 35000)); //30ms
				Updates::informUsers($users_tables);
				usleep(500000); //500ms
			}
			if($try<=0){
				$time = $this->freshTimestamp();
				$this::where('id', $this->id)->getQuery()->update(['progress' => 100, 'size' => $size, 'error' => 1, 'updated_at' => $time, 'extra' => null]);
				usleep(rand(30000, 35000)); //30ms
				Updates::informUsers($users_tables);
			}

		}
	}

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array()){
		//Skip if it already exists
		if(isset($links[$this->getTable()][$this->id])){
			return array(null, $links);
		}
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
		}

		//Skip file versioning and files that are not part of a project
		if($this->version!=0 || $this->parent_type!='projects'){
			return array(null, $links);
		}

		$clone = $this->replicate();
		$clone->forceGiveAccess();

		$clone->created_by = $uid;
		if(!is_null($clone->deleted_by)){ $clone->deleted_by = $uid; }
		foreach ($attributes as $key => $value) {
			$clone->$key = $value;
		}
		//Initialization of attributes
		$clone->temp_id = '';
		if(!is_null($clone->deleted_at)){
			$clone->deleted_at = Carbon::createFromFormat('Y-m-d H:i:s', $clone->deleted_at)->addSeconds($offset);
		}
		$clone->viewed_by = '';
		$clone->_perm = '';
		$clone->extra = null;
		$clone->puid = $this->created_by;
		if(!is_null($this->puid)){
			$clone->puid = $this->puid;
		}

		//Pivots
		$pivots = new \stdClass;
		$dependencies_visible = $clone::getDependenciesVisible();
		$extra = $this->extraDecode();
		foreach ($dependencies_visible as $dep => $value) {
			if(!isset($exclude_links[$dep]) && isset($dependencies_visible[$dep][1])){
				if($extra && (!isset($extra->{'_'.$dep}) || empty($extra->{'_'.$dep}))){
					continue;
				}
				$items = $this->$dep; //Use the relation table
				foreach ($items as $item) {
					$table = $item->getTable();
					if(isset($links[$table][$item->id])){
						if(!isset($pivots->{$dep.'>access'})){ $pivots->{$dep.'>access'} = new \stdClass; }
						$pivots->{$dep.'>access'}->{$links[$table][$item->id]->id} = $item->pivot->access;
						foreach ($dependencies_visible[$dep][1] as $field) {
							if(isset($item->pivot->$field)){
								if(!isset($pivots->{$dep.'>'.$field})){ $pivots->{$dep.'>'.$field} = new \stdClass; }
								$pivots->{ $dep.'>'.$field}->{$links[$table][$item->id]->id} = $item->pivot->$field;
								//If it's a Carbon object, we add the offset
								if($offset!=0){
									if(preg_match("/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/ui", $item->pivot->$field)){
										try {
											$item->pivot->$field = Carbon::createFromFormat('Y-m-d H:i:s', $item->pivot->$field)->addSeconds($offset);
										} catch (\Exception $e) {}
									}
								}
							}
						}
					}
				}
			}
		}
		$clone->pivots_format($pivots, false);

		$clone->saveHistory(false);
		$clone->parentSave(); //Specific to Files to skip file copy
		$links[$this->getTable()][$this->id] = $clone;
		if(static::$permission_sheet[0]){ //Permission of owner
			self::$permission_users[$uid][$clone->getTable()][$clone->id] = static::$permission_sheet[0];
		}

		//Clone comments (no dependencies)
		if(!isset($exclude_links['comments'])){
			$attributes = array(
				'parent_type' => 'files',
				'parent_id' => $clone->id,
			);
			if($comments = $this->comments){
				foreach ($comments as $comment) {
					$comment->clone($offset, $attributes, $links);
				}
			}
		}

		return $clone; //$link is directly modified as parameter &$link
	}
	
	public function parentSave(){
		return parent::save();
	}


	/////////////////////////////

	//Return the true file extension if it has be artificially modified (works only for pictures)
	//http://stackoverflow.com/questions/1282351/what-kind-of-file-types-does-php-getimagesize-return
	//$file must be a full link
	protected function fileformat(){
		$this->ori_ext = false;
		if(is_file($this->tmp_name) && filesize($this->tmp_name)!==false){
			/*
			[IMAGETYPE_GIF] => 1
			[IMAGETYPE_JPEG] => 2
			[IMAGETYPE_PNG] => 3
			[IMAGETYPE_SWF] => 4
			[IMAGETYPE_PSD] => 5
			[IMAGETYPE_BMP] => 6
			[IMAGETYPE_TIFF_II] => 7
			[IMAGETYPE_TIFF_MM] => 8
			[IMAGETYPE_JPC] => 9
			[IMAGETYPE_JP2] => 10
			[IMAGETYPE_JPX] => 11
			[IMAGETYPE_JB2] => 12
			[IMAGETYPE_SWC] => 13
			[IMAGETYPE_IFF] => 14
			[IMAGETYPE_WBMP] => 15
			[IMAGETYPE_JPEG2000] => 9
			[IMAGETYPE_XBM] => 16
			[IMAGETYPE_ICO] => 17
			[IMAGETYPE_UNKNOWN] => 0
			[IMAGETYPE_COUNT] => 18 
			*/
			$formattab = array(
			1 => 'gif',
			2 => 'jpg',
			3 => 'png',
			4 => 'swf',
			5 => 'psd',
			6 => 'bmp',
			7 => 'tif',
			8 => 'tif',
			9 => 'jpc',
			10 => 'jp2',
			11 => 'jpf',
			12 => 'jb2',
			13 => 'swc',
			14 => 'aiff',
			15 => 'wbmp',
			16 => 'xbm',
			//[IMAGETYPE_ICO] => 17 => "ico", //mpeg videos are detected as IMAGETYPE_ICO !!
			//[IMAGETYPE_UNKNOWN] => 0 => "", //No need to use it
			//[IMAGETYPE_COUNT] => 18 => "" //I don't know what it is
			);
			if(strstr($this->name, '.')){
				$this->ori_ext = mb_strtolower(substr($this->name,strrpos($this->name, ".")+1));
			}
			
			if(filesize($this->tmp_name)>=12){ //Because getimagesize bug below de 12 bytes (can try with .txt "12345678901")
				if($size = getimagesize($this->tmp_name)){
					$tab = $size[2];
					if(array_key_exists($tab, $formattab)){
						$this->ori_ext = $formattab[$tab];
					}
				}
			}
		}
		return $this->ori_ext;
	}


	//IPTC data list
	protected function output_iptc_data( $image_path ) {
		$info = 0;
			if(is_file($image_path) && filesize($image_path)>=12){
				$size = getimagesize ( $image_path, $info);
			}
		$list = "";
		if(is_array($info)) {
			$iptc = iptcparse($info['APP13']);
			foreach (array_keys($iptc) as $s) {
				$c = count ($iptc[$s]);
				for ($i=0; $i <$c; $i++)
				{
					$list.=$iptc[$s][$i].'<br />';
				}
			}
		}
		return $list;
	}

	//EXIF data list
	protected function output_exif_data($image_path){
		$exif = exif_read_data($image_path);
		$list = "";
		foreach ($exif as $key => $section) {
			if(is_array($section)){
				foreach ($section as $name => $val) {
					$list .= "$key.$name: $val <br />";
				}
			} else {
				$list .= "$key : $section <br />";
			}
		}
		return $list;
	}

	//Return a Unix date if the fiel IPTC has been filled
	protected function UnixIPTCDdate($filetp){
		$unixDate = false;
		if(is_file($filetp) && filesize($filetp)>=12){
			$size = getimagesize($filetp, $info);
			if (!empty($filetp) && is_file($filetp) && $size[2]==2 && isset($info['APP13'])){ //$size[2]==2 is JPEG
				if($iptc = iptcparse($info['APP13'])){
					if(isset($iptc['2#055'][0]) && isset($iptc['2#060'][0])){
						$YMD = $iptc['2#055'][0];
						$HMS = $iptc['2#060'][0];
						$unixDate = mktime(substr($HMS,0,2),substr($HMS,2,2),substr($HMS,4,2),substr($YMD,4,2),substr($YMD,6,2),substr($YMD,0,4));
					}
				}
			}
		}
		return $unixDate;
	}


	//Return UNIX timestamp date
	protected function convertDate($filetp){
		if(is_file($filetp) && filesize($filetp)>=12){
			$size = getimagesize($filetp, $info);
			if($unixIPTC=UnixIPTCDdate($filetp)){
				return $unixIPTC;
			}	else if($size[2]==2 && $exif=@exif_read_data($filetp)){
				if (isset($exif['EXIF']['DateTimeOriginal'])){
					$tpdate = $exif['EXIF']['DateTimeOriginal'];
					$tpdate = mktime(substr($tpdate,11,2),substr($tpdate,14,2),substr($tpdate,17,2),substr($tpdate,5,2),substr($tpdate,8,2),substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['DateTimeOriginal'])){
					$tpdate = $exif['DateTimeOriginal'];
					$tpdate = mktime(substr($tpdate,11,2),substr($tpdate,14,2),substr($tpdate,17,2),substr($tpdate,5,2),substr($tpdate,8,2),substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['EXIF']['DateTimeDigitized'])){
					$tpdate = $exif['EXIF']['DateTimeDigitized'];
					$tpdate = mktime(substr($tpdate,11,2),substr($tpdate,14,2),substr($tpdate,17,2),substr($tpdate,5,2),substr($tpdate,8,2),substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['DateTimeDigitized'])){
					$tpdate = $exif['DateTimeDigitized'];
					$tpdate = mktime(substr($tpdate,11,2),substr($tpdate,14,2),substr($tpdate,17,2),substr($tpdate,5,2),substr($tpdate,8,2),substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['IFD0']['DateTime'])){
					$tpdate = $exif['IFD0']['DateTime'];
					$tpdate = mktime(substr($tpdate,11,2),substr($tpdate,14,2),substr($tpdate,17,2),substr($tpdate,5,2),substr($tpdate,8,2),substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['DateTime'])){
					$tpdate = $exif['DateTime'];
					$tpdate = mktime(substr($tpdate,11,2),substr($tpdate,14,2),substr($tpdate,17,2),substr($tpdate,5,2),substr($tpdate,8,2),substr($tpdate,0,4));
					return $tpdate;
				}	else if (filectime($filetp)) { //File creation date
					return filectime($filetp);
				}	else if (isset($exif['FILE']['FileDateTime'])){
					return $exif['FILE']['FileDateTime']; //File modification date
				}	else if (isset($exif['FileDateTime'])){
					return $exif['FileDateTime']; //File modification date
				} else {
					return time();
				}
			} else if (filectime($filetp)) {
				return filectime($filetp);
			} else {
				return time();
			}
		} else {
			return time();
		}
	}


}
