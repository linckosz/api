<?php

namespace libs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

class Translation {

	const LANG = 'en';

	protected $app = NULL;
	protected $bundle = NULL;
	protected $default_lang = 'en';
	protected $lang = array();
	protected $list = array();
	protected $listfull = array();

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
						$list[$bundle][$value->category][$value->phrase] = self::pushData($value->$lang);
					}
				}
			}
		}
		return $list;
	}

	public function getClientLanguage(){
		$app = $this->app = \Slim\Slim::getInstance();
		$json = json_decode($app->request->getBody());
		if(isset($json->language)){
			return $json->language;
		} else if($app->getCookie('yuyan', false)){
			return $app->getCookie('yuyan', false);
		} else if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match("/([\w]{2,})(?:$|\W)/ui", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $match)){
			return $match[1];
		}
		return self::LANG;
	}

	public function getLanguages($bundle = NULL){
		if($bundle){
			$this->bundle = $bundle;
			return $this->getListfull();
		} else {
			return $this->listfull;
		}
	}

	public function setDefaultLanguage($lang = NULL){
		if(preg_match_all("/^[a-z]{2}[a-z-]{0,6}$/ui", mb_strtolower($lang))){
			$this->default_lang = mb_strtolower($lang);
		} else {
			$this->default_lang = self::LANG;
		}
		return true;
	}

	protected static function pushData($text){
		$app = \Slim\Slim::getInstance();
		$trans = $app->lincko->translation;
		if(count($trans)>0){
			preg_match_all("/@@(.+?)~~/u", $text, $matches, PREG_SET_ORDER);
			foreach($matches as $value) {
				if(isset($trans[$value[1]])){
					$replace = $trans[$value[1]];
				} else {
					$replace = '[unknown value]';
					\libs\Watch::php('The word could not be converted: '.$value[1]." \n".$text,'Translation::get',__FILE__,true);
				}
				$text = str_replace($value[0], $replace, $text);
			}
		}
		return $text;
	}

	protected function get($bundle, $category, $phrase, $data){
		$app = \Slim\Slim::getInstance();
		$app->lincko->translation = array_merge(
			$app->lincko->translation,
			$data
		);
		$this->bundle = $bundle;
		$this->setLanguage();
		if(isset($this->lang[$bundle])){
			$lang = $this->lang[$bundle];
			if($value = TranslationModel::on($bundle)->where('category', '=', $category)->where('phrase', '=', $phrase)->first(array($lang))){
				$value = $value->getAttribute($lang);
				$value = self::pushData($value);
				return $value;
			}
		}
		\libs\Watch::php('The translation does not exist: '.$bundle.' | '.$category.' | '.$phrase,'Translation::get',__FILE__,true);
		return false;
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
		if($app->getCookie('yuyan', false)){
			$langlong = $app->getCookie('yuyan', false);
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
		$this->default_lang = $this->lang[$bundle];
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
				$listfull[$value['Field']] = $value['Comment'];
			}
		}
		return $listfull;
	}

}

class TranslationModel extends Model {

	protected $table = 'translation';

	public $timestamps = false;

	/////////////////////////////////////

	//"READ ONLY" mode
	//Overwrite the basic function save() to not update or add anymore line
	public function save(array $options = array()){
		return false;
	}

}