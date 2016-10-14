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
	if(isset($_COOKIE) && isset($_COOKIE['PHPSESSID']) && !empty($_COOKIE['PHPSESSID'])){
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
		ini_set("session.cookie_domain", '.'.$app->lincko->domain); //same session id across sub-domain
		session_start();
	}

}

Handler::session_initialize();
