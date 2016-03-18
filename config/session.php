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
	return Session::find($id)->delete();
}

function _clean($max){
	$old = time() - $max;
	return Session::where('access', '<', $old)->delete();
}

function _donothing(){
	return true;
}

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
session_start();
