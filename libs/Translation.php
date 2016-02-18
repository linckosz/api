<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;
use \libs\OneSeventySeven;

class Translation {

	const LANG = 'en';
	//http://www.red-route.org/code/php-international-language-and-locale-codes-demonstration
	const LOCALE = 'en_US.utf8';

	protected $app = NULL;
	protected $bundle = NULL;
	protected $default_lang = 'en';
	protected $lang = array();
	protected $list = array();
	protected $listfull = array();
	protected $translation = array();

	protected $default_locale = 'en_US.utf8';
	protected $default_locale_list = array(
		'en' => 'en_US.utf8',
		'fr' => 'fr_FR.utf8',
		'zh-chs' => 'zh_CN.utf8',
	);

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
	}

	public function getHTML($bundle, $category, $phrase, array $data = array()){
		$result = $this->get($bundle, $category, $phrase, $data);
		$result = STR::sql_to_html($result);
		return $result;
	}

	public function getINPUT($bundle, $category, $phrase, array $data = array()){
		$result = $this->getHTML($bundle, $category, $phrase, $data);
		return $result;
	}

	public function getTEXTAREA($bundle, $category, $phrase, array $data = array()){
		$result = $this->getBRUT($bundle, $category, $phrase, $data);
		return $result;
	}

	public function getJS($bundle, $category, $phrase, array $data = array()){
		$result = $this->get($bundle, $category, $phrase, $data);
		$result = STR::sql_to_js($result);
		return $result;
	}

	public function getJSON($bundle, $category, $phrase, array $data = array()){
		$result = $this->getJS($bundle, $category, $phrase, $data);
		return $result;
	}

	public function getBRUT($bundle, $category, $phrase, array $data = array()){
		$result = $this->get($bundle, $category, $phrase, $data);
		return $result;
	}

	public function getList($bundle=true, $category=true){
		$app = $this->app;
		$list = array();
		$arr_bundles = array();
		if($bundle===true){
			foreach ($app->lincko->databases as $bundle => $value) {
				$arr_bundles[$bundle] = true;
			}
		} else if(is_string($bundle) && isset($app->lincko->databases[$bundle])){
			$arr_bundles[$bundle] = true;
		}

		foreach ($arr_bundles as $bundle => $value) {
			$this->bundle = $bundle;
			$this->setLanguage();
			if(isset($this->lang[$bundle])){
				$lang = $this->lang[$bundle];
				$result = false;
				if($category===true){
					$result = TranslationModel::on($bundle)->get(array('category', 'phrase', $lang));
				} else if(is_int($category)){
					$result = TranslationModel::on($bundle)->where('category', '=', $category)->get(array('category', 'phrase', $lang));
				}
				if($result){
					foreach ($result as $key => $value) {
						//Record all sentence, but not with coverted variables to save CPU time
						$list[$bundle][$value->category][$value->phrase] = $value->$lang;
					}
				}
			}
		}
		$this->translation = array_merge(
			$this->translation,
			$list
		);
		return $list;
	}

	public function getClientLanguage(){
		$app = $this->app;
		$json = json_decode($app->request->getBody());
		if(isset($json->language)){
			return $json->language;
		} else if(OneSeventySeven::get('yuyan')){
			return OneSeventySeven::get('yuyan');
		} else if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match("/([\w]{2,})(?:$|\W)/ui", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $match)){
			return $match[1];
		}
		return self::LANG;
	}

	public function getClientLanguageFull($bundle = NULL){
		$listfull = $this->listfull;
		$langshort = $this->getClientLanguage();
		if($bundle){
			$this->bundle = $bundle;
			if(!isset($this->list[$bundle])){
				$listfull = $this->getListfull();
			}
		}
		if(isset($listfull[$langshort])){
			return $listfull[$langshort];
		}
		return NULL;
	}

	public function getLanguages($bundle = NULL){
		if($bundle){
			$this->bundle = $bundle;
			return $this->getListfull();
		} else {
			return $this->listfull;
		}
	}

	public function setDefaultLanguage($bundle = NULL){
		if(is_string($bundle) && isset($app->lincko->databases[$bundle])){
			$this->bundle = $bundle;
			$this->setLanguage();
		}
		return true;
	}

	protected function setDefaultLocale($bundle){
		$app = $this->app;
		if(isset($app->lincko->databases[$bundle])){
			$this->bundle;
			$lang = $this->default_lang;
			if(array_key_exists($lang, $this->default_locale_list)){
				$this->default_locale = $this->default_locale_list[$lang];
			} else {
				$this->default_locale = self::LOCALE;
			}
			setlocale(LC_ALL, $this->default_locale);
			return true;
		}
		return false;
	}

	protected static function pushData($text, $watch=true){
		$app = \Slim\Slim::getInstance();
		$trans = $app->lincko->translation;
		if(count($trans)>0){
			preg_match_all("/@@(\S+?)~~/u", $text, $matches, PREG_SET_ORDER);
			foreach($matches as $value) {
				$replace = '[unknown value]';
				$result = strtolower($value[1]);
				$array = preg_split("/\|/", $result, 2);
				if(isset($trans[$array[0]])){ //Get the word
					$replace = $trans[$array[0]];
					if(isset($array[1])){ //Active teh filter
						$replace = self::filter($replace, $array[1]);
					}
				} else if($watch){
					\libs\Watch::php('The word could not be converted: '.$array[0]." \n".$text,'Translation::get',__FILE__,true);
				}
				$text = str_replace($value[0], $replace, $text);
			}
		}
		return $text;
	}

	protected static function filter($text, $filters){
		$array = preg_split("/\|/", $filters);
		foreach($array as $filter) {
			$filter = strtolower($filter);
			if($filter === 'lower'){
				$text = strtolower($text);
			} else if($filter === 'upper'){
				$text = strtoupper($text);
			} else if($filter === 'ucfirst'){
				$text = ucfirst($text);
			}
		}
		return $text;
	}

	protected function get($bundle, $category, $phrase, $data){
		$app = $this->app;
		$value = false;
		if(!empty($data)){
			$app->lincko->translation = array_merge(
				$app->lincko->translation,
				$data
			);
		}
		if(isset($app->lincko->databases[$bundle])){
			if(isset($this->translation[$bundle][$category][$phrase])){
				$value = $this->translation[$bundle][$category][$phrase];
			} else {
				$this->getList($bundle);
				$this->bundle = $bundle;
				$this->setLanguage();
				if(isset($this->translation[$bundle][$category][$phrase])){
					$value = $this->translation[$bundle][$category][$phrase];
				} else if(isset($this->lang[$bundle])){
					$lang = $this->lang[$bundle];
					if($value = TranslationModel::on($bundle)->where('category', '=', $category)->where('phrase', '=', $phrase)->first(array($lang))){
						$value = $value->getAttribute($lang);
					}
				}
			}
			$value = self::pushData($value);
			return $value;
		}
		\libs\Watch::php('The translation does not exist: '.$bundle.' | '.$category.' | '.$phrase,'Translation::get',__FILE__,true);
		return flase;
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
				$keylong = $value->Field;
				$keyshort = preg_replace("/-.*/ui", '', $keylong);
				if(!isset($list[$keylong])){
					$list[$keylong] = $value->Field;
				}
				if(!isset($list[$keyshort])){
					$list[$keyshort] = $value->Field;
				}
				$this->listfull[$value->Field] = $value->Comment;
			}
		}
		return true;
	}

	protected function getBodyLanguage(){
		$app = $this->app;
		$bundle = $this->bundle;
		$list = $this->list[$bundle];
		$json = json_decode($app->request->getBody());
		if(isset($json->language)){
			$langlong = $json->language;
			$langshort = preg_replace("/-.*/ui", '', $langlong);
			if(isset($list[$langlong])){
				return $list[$langlong];
			}
			if(isset($list[$langshort])){
				return $list[$langshort];
			}
		}
		return false;
	}

	protected function getCookieLanguage(){
		$app = $this->app;
		$bundle = $this->bundle;
		$list = $this->list[$bundle];
		if($langlong = OneSeventySeven::get('yuyan')){
			$langshort = preg_replace("/-.*/ui", '', $langlong);
			if(isset($list[$langlong])){
				return $list[$langlong];
			}
			if(isset($list[$langshort])){
				return $list[$langshort];
			}
		}
		return false;
	}

	protected function getBrowserLanguage(){
		$bundle = $this->bundle;
		$list = $this->list[$bundle];
		if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
			preg_match_all("/([\w-]{2,})(?:$|\W)/ui", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches, PREG_SET_ORDER);
			foreach($matches as $key => $value) {
				$langlong = $matches[$key][1];
				$langshort = preg_replace("/-.*/ui", '', $langlong);
				if(isset($list[$langlong])){
					return $list[$langlong];
				}
				if(isset($list[$langshort])){
					return $list[$langshort];
				}
			}
		}
		return false;
	}

	protected function getDefaultLanguage(){
		$app = $this->app;
		$bundle = $this->bundle;
		$list = $this->list[$bundle];
		$langlong = $this->default_lang;
		$langshort = preg_replace("/-.*/ui", '', $langlong);
		if(isset($list[$langlong])){
			return $list[$langlong];
		}
		if(isset($list[$langshort])){
			return $list[$langshort];
		}
		return false;
	}

	protected function setLanguage(){
		$bundle = $this->bundle;
		if(!$this->setList()){
			return false;
		}
		$tp = false;
		if(isset($this->lang[$bundle])){
			//Do nothing
		} else if($tp = $this->getBodyLanguage()){
			$this->lang[$bundle] = $tp;
		} else if($tp = $this->getCookieLanguage()){
			$this->lang[$bundle] = $tp;
		} else if($tp = $this->getBrowserLanguage()){
			$this->lang[$bundle] = $tp;
		} else if($tp = $this->getDefaultLanguage()){
			$this->lang[$bundle] = $tp;
		} else {
			$this->lang[$bundle] = self::LANG;
		}

		if(preg_match_all("/^[a-z]{2}[a-z-]{0,6}$/ui", mb_strtolower($this->lang[$bundle]))){
			$this->default_lang = mb_strtolower($this->lang[$bundle]);
		} else {
			$this->default_lang = self::LANG;
		}

		$this->setDefaultLocale($bundle);
		return true;
	}

	protected function getListfull(){
		$app = $this->app;
		$bundle = $this->bundle;
		$listfull = array();
		if(isset($app->lincko->databases[$bundle]) && Capsule::schema($bundle)->hasTable('translation')){
			$sql = 'SHOW FULL COLUMNS FROM `translation` WHERE LOWER(`Type`)=\'text\';';
			$db = Capsule::connection($bundle);
			$data = $db->select( $db->raw($sql) );
			foreach ($data as $key => $value) {
				$listfull[$value->Field] = $value->Comment;
			}
		}
		return $listfull;
	}

}

class TranslationModel extends Model {

	protected $table = 'translation';

	public $timestamps = false;

	/////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

	//"READ ONLY" mode
	//Overwrite the basic function save() to not update or add anymore line
	public function save(array $options = array()){ return false; }

	protected function checkDeployment(){
		$app = \Slim\Slim::getInstance();
		if( isset($app->lincko->deployment) && password_verify($app->lincko->deployment, '$2y$10$J6gakNmqkjrpnyMFJHhyq.JQves6JslSHJLKqpWXfZVJ6qpDKDXK6') ){
			return true;
		}
		return false;
	}

	public static function queryInsert($attributes){
		$return = false;
		if($this->checkDeployment()){
			$return = TranslationModel::on($bundle)->insert($attributes);
			return $return;
		}
		return false;
	}

	public function querySave(){
		$return = false;
		if($this->checkDeployment()){
			$dirty = $this->getDirty();
			if(count($dirty) > 0){
				$return = $this->where('category', $this->category)->where('phrase', $this->phrase)->getQuery()->update($dirty);
			}
		}
		return $return;
	}

}
