<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Projects;
use \libs\Json;
use \libs\Folders;
use \libs\Video;
use \libs\IptcManager;
use \libs\SimpleImage;
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
		'progress',
		'_parent',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected $show_field = 'name';

	protected $search_fields = array(
		'name',
	);

	protected $name_code = 900;

	protected $archive = array(
		'created_at' => 901, //[{un|ucfirst}] created a new file
		'_' => 902, //[{un|ucfirst}] modified a file
		'name' => 903, //[{un|ucfirst}] changed a file name
		'comment' => 904, //[{un|ucfirst}] modified a file description
		'pivot_access_0' => 996, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a file
		'pivot_access_1' => 997, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a file
		'_restore' => 998,//[{un|ucfirst}] restored a file
		'_delete' => 999,//[{un|ucfirst}] deleted a file
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	/*
		IMPORTANT:
		'users', 'chats', 'projects' are hardly attached
		'tasks', 'notes' are softly attached, meaning that the file will be attached to the parent project but will be given the dependency to the tasks or note
	*/
	protected static $parent_list = array('users', 'chats', 'projects', 'tasks', 'notes');
	protected static $parent_list_hard = array('users', 'chats', 'projects');

	protected $model_integer = array(
		'version_of',
		'size',
		'width',
		'height',
		'progress',
	);

	protected static $allow_single = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);
	
////////////////////////////////////////////

	protected $imagequalitysize = '1920'; //1GB => 3,500 pictures
	protected $imagequalitycomp = '70';
	protected $videoquality = 1; //[0]480p / [1]720p / [2]1080p

	//public $category = 'file'; //Store in file by default
	protected static $list_categories = array(
		'image' => array('image/bmp', 'image/x-windows-bmp', 'image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/vnd.wap.wbmp'),

		'video' => array('application/asx', 'application/vnd.ms-asf', 'application/vnd.rn-realmedia', 'application/vnd.rn-realmedia-vbr', 'application/x-mplayer2', 'application/x-pn-mpg', 'application/x-troff-msvideo', 'content/unknown', 'image/mov', 'image/mpg', 'video/3gpp', 'video/avi', 'video/dvd', 'video/mp4', 'video/mp4v-es', 'video/mpeg', 'video/mpeg2', 'video/mpg', 'video/msvideo', 'video/quicktime', 'video/xmpg2', 'video/x-flv', 'video/x-m4v', 'video/x-matroska', 'video/x-mpeg', 'video/x-mpeg2a', 'video/x-mpg', 'video/x-msvideo', 'video/x-ms-asf', 'video/x-ms-asf-plugin', 'video/x-ms-wm', 'video/x-ms-wmv', 'video/x-ms-wmx', 'video/x-quicktime', 'video/webm'),

		'audio' => array('audio/3gpp', 'audio/aiff', 'audio/asf', 'audio/avi', 'audio/mp4', 'audio/mpeg', 'audio/vnd.rn-realaudio', 'audio/x-midi', 'audio/x-mpeg', 'audio/x-pm-realaudio-plugin', 'audio/x-pn-realaudio', 'audio/x-realaudio', 'audio/x-wav', 'audio/webm'),
	);

////////////////////////////////////////////

	//Many(Files) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_files', 'files_id', 'users_id')->withPivot('access');
	}

	//Many(Files) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'files_x_files', 'files_id', 'tasks_id')->withPivot('access');
	}

	//Many(Files) to Many(Notes)
	public function notes(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'notes_x_files', 'files_id', 'notes_id')->withPivot('access');
	}

	//Many(Files) to Many(Comments)
	public function comments(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'comments', 'id', 'parent_id');
	}

	//Many(Files) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'comments', 'id', 'parent_id');
	}

	//One(Files) to Many(Users)
	public function profile(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Users', 'profile_pic');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_type) && !self::validType($form->parent_type, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->name) && !self::validTitle($form->name, true))
			|| (isset($form->comment) && !self::validText($form->comment, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//This is used because by default not all IDs are stored in pivot table
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array()){
		$default = array(
			'access' => 1, //Default is accessible
		);
		foreach ($uid_list as $uid) {
			if(!isset($result[$uid])){ $result[$uid] = array(); }
			foreach ($list as $value) {
				if(!isset($result[$uid][$value])){
					$result[$uid][$value] = (array) $default;
				}
			}
		}
		return $result;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		//It will get all roles with access 1, and all roles which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) use ($list) {
			foreach ($list as $table_name => $list_id) {
				if(in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
					$this->var['parent_type'] = $table_name;
					$this->var['parent_id_array'] = $list_id;
					$query = $query
					->orWhere(function ($query) {
						$query
						->where('files.parent_type', $this->var['parent_type'])
						->whereIn('files.parent_id', $this->var['parent_id_array']);
					});
					if($table_name=='users'){
						$query = $query
						->orWhereHas('profile', function ($query){
							//Toto => This is a problem, it get all pictures profile from all users
						});
					}
				}
			}
		})
		->whereHas("users", function($query) {
			$app = self::getApp();
			$query
			->where('users_id', $app->lincko->data['uid'])
			->where('access', 0);
		}, '<', 1);
		if(self::$with_trash_global){
			$query = $query->withTrashed();
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

	public function checkAccess($show_msg=true){ //toto
		return true;
	}

	public function checkPermissionAllow($level, $msg=false){ //toto
		return true;
	}

	
	public function save(array $options = array()){
		$app = self::getApp();
		$new = false;
		if(!$this->id){ //Only copy a file for new items
			if($this->error!=0 || !$this->fileformat()){
				return false;
			}
			if($this->size > 1000000000){
				$msg = $app->trans->getBRUT('api', 3, 7); //File too large
				$json = new Json($msg, true, 400);
				$json->render(400);
				return false;
			}
			try {
				$this->server_path = $app->lincko->filePath;
				$this->setCategory();
				$this->link = md5(uniqid());
				$folder_ori = new Folders;
				$folder_ori->createPath($this->server_path.'/'.$app->lincko->data['uid'].'/');
				$this->thu_type = null;
				$this->thu_ext = null;
				$this->progress = 100;
				if($this->category=='image'){
					copy($this->tmp_name, $folder_ori->getPath().$this->link);
					$folder_thu = new Folders;
					$folder_thu->createPath($this->server_path.'/'.$app->lincko->data['uid'].'/thumbnail/');
					try {
						$this->thu_type = 'image/jpeg';
						$this->thu_ext = 'jpg';
						$src = WideImage::load($this->tmp_name);
						$src = $src->resize(256, 256, 'inside', 'any');
						$src = $src->saveToFile($folder_thu->getPath().$this->link.'.jpg', 60);
						rename($folder_thu->getPath().$this->link.'.jpg', $folder_thu->getPath().$this->link);
					} catch(\Exception $e){
						\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, true);
						$this->thu_type = 'image/png';
						$this->thu_ext = 'png';
						copy($app->lincko->path.'/bundles/lincko/api/public/images/generic/unavailable.png', $folder_thu->getPath().$this->link);
					}
				} else if($this->category=='video'){
					$this->progress = 0; //Only video needs significant time for compression
					$this->size = 0;
					$folder_thu = new Folders;
					$folder_thu->createPath($this->server_path.'/'.$app->lincko->data['uid'].'/thumbnail/');
					$folder_txt = new Folders;
					$folder_txt->createPath($this->server_path.'/'.$app->lincko->data['uid'].'/convert/');
					$this->thu_type = 'image/jpeg';
					$this->thu_ext = 'jpg';
					$video = new Video($this->tmp_name, $folder_ori->getPath().$this->link, $folder_thu->getPath().$this->link, $folder_txt->getPath().$this->link);
					if($video->thumbnail()!==0){
						return false;
					}
					if($video->convert(2)!==0){
						return false;
					}
				} else {
					copy($this->tmp_name, $folder_ori->getPath().$this->link);
					//No thumbnail for other kind of files
				}
				$new = true;
			} catch(\Exception $e){
				\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, true);
				return false;
			}
		}
		$return = parent::save($options);

		if($new && $this->category=='video'){
			sleep(1); //wait 1s to make sure the conversion is starting
			$url = $app->environment['slim.url_scheme'].'://'.$app->request->headers->Host.'/file/progress/'.$this->id;
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, null);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json; charset=UTF-8',
					'Content-Length: ' . mb_strlen(null),
				)
			);
			curl_exec($ch);
			@curl_close($ch);
		}
		
		return $return;
	}


	public static function getParentListHard(){
		return static::$parent_list_hard;
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

	public function setProgress(){
		if($this->category == 'video' && $this->progress < 100){

		}
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
			if(filesize($image_path)>=12){
				$size = getimagesize ( $image_path, $info);
			}
		$list = "";
		if(is_array($info)) {
			$iptc = iptcparse($info["APP13"]);
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
