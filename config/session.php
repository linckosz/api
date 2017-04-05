<?php
namespace config;

use \libs\Session;

function _open(){
	return true;
}

function _close(){
	return true;
}

function _read($id){
	$id = get_session_id();
	$sess = Session::find($id);
	if($sess){
		//It's useless to save this time access
		//$sess->access = time();
		//$sess->save();
		return $sess->data;
	}
	return '';
}

function _write($id, $data){
	$id = get_session_id();
	$sess = Session::find($id);
	if($sess){
		if(isset($GLOBALS['unset_sess']) && is_array($GLOBALS['unset_sess'])){
			/*
			This help to have 2 processes running in parallel to overwrite the other one:
			  - [P1] start
			  - [P1] sess['aaa']=1
			  - [P2] start
			  - [P2] sess['bbb']=2
			  - [P1] end
			  - [P2] end
			  - [P3] start => before only sess['bbb'] was existing because P2 overwritten the P1 change, now P3 has 'aaa' nad 'bbb'
			*/
			//Grab current database status
			if($current = @unserialize($sess->data)){
				//Overwrite with current values
				foreach ($_SESSION as $key => $value) {
					unset($GLOBALS['unset_sess'][$key]);
					$current[$key] = $_SESSION[$key];
				}
				//Unset values from what's left from $GLOBALS['unset_sess']
				foreach ($GLOBALS['unset_sess'] as $key => $value) {
					unset($current[$key]);
				}
				//Serialize the new data
				$data = serialize($current);
			}
		}
		$sess->access = time();
		$sess->data = $data;
		$sess->save();
		return true;
	} else {
		$sess = new Session;
		$sess->id = $id;
		$sess->access = time();
		$sess->data = $data;
		$sess->save();
		return true;
	}
	return false;
}

function _destroy($id){
	$id = get_session_id();
	if(Session::find($id)->delete()){
		return true;
	}
	return false;
}

function _clean($max){
	$old = time() - $max;
	if(Session::where('access', '<', $old)->delete()){
		return true;
	}
	return false;
}

function _donothing(){
	return true;
}

function get_session_id(){
	if(isset($_SERVER) && isset($_SERVER['LINCKO_PHPSESSID']) && !empty($_SERVER['LINCKO_PHPSESSID'])){
		return $_SERVER['LINCKO_PHPSESSID'];
	} else if(isset($_COOKIE) && isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID'])){
		return $_COOKIE['PHPSESSID'];
	} else {
		return session_id();
	}
}

class Handler {

	public static function session_initialize($enableSession=false){
		$app = \Slim\Slim::getInstance();
		if($enableSession){
			$app->lincko->enableSession = true;
		} else {
			$enableSession = $app->lincko->enableSession;
		}
		session_write_close();
		if($app->lincko->enableSession){
			session_set_save_handler(
				'config\_open',
				'config\_close',
				'config\_read',
				'config\_write',
				'config\_destroy',
				'config\_clean'
			);
		} else {
			session_set_save_handler(
				'config\_donothing',
				'config\_donothing',
				'config\_donothing',
				'config\_donothing',
				'config\_donothing',
				'config\_donothing'
			);
		}
		session_cache_limiter(false);
		ini_set('session.auto_start', '1');
		ini_set('session.serialize_handler', 'php_serialize');
		ini_set("session.cookie_domain", '.'.$app->lincko->domain); //same session id across sub-domain
		session_start();
		$GLOBALS['unset_sess'] = $_SESSION; //Keep track of old status of Session
	}

}

Handler::session_initialize();
