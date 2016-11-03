<?php

namespace bundles\lincko\api\models;

use Carbon\Carbon;
use JPush\Client as JPush;

class Notif {

	protected static $app = NULL;
	protected static $client = NULL;

	const APP = '38e30d8c93c0ef79d9dc5cc4';
	const MASTER = '063defc2edb491bf32aff53f'; 

	public function __construct(){
		if(is_null(self::$app)){
			self::$client = new JPush(self::APP, self::MASTER, '/tmp/toto');
		}
		return true;
	}

	public static function getApp(){
		if(is_null(self::$app)){
			self::$app = \Slim\Slim::getInstance();
		}
		return self::$app;
	}

	public function push($msg, $notif=array(), $tag=array(), $regId=array()){
		$client = self::$client;
		$response = $client->push();
		$response->setPlatform('all');
		if(!empty($regId)){
			$response->addRegistrationId($regId);
		} else {
			$response->addAllAudience();
		}
		if(!empty($regId)){
			$response->addAllAudience();
		}
		$response->iosNotification($msg, $notif);
		$response->androidNotification($msg, $notif);
		//Setup default for winPhone
		if(!isset($notif['title'])){ $notif['title'] = null; }
		if(!isset($notif['_open_page'])){ $notif['_open_page'] = null; }
		if(!isset($notif['extras'])){ $notif['extras'] = null; }
		$response->addWinPhoneNotification($msg, $notif['title'], $notif['_open_page'], $notif['extras']);
		try {
			$response->send();
		} catch (\JPush\Exceptions\JPushException $e) {
			\libs\Watch::php($e, 'JPushException', __FILE__, true); //error
		}
	}

	public function sample(){
		$msg = 'Cool, it works!!!!';
		$notif = array(
			'sound' => 'hello ios sound',
			'title' => 'hello android-winP title',
			'badge' => 2,
			'build_id' => 2,
			'content-available' => true,
			'category' => 'hello ios category',
			'_open_page' => 'winPage',
			'extras' => array(
				'type' => 'tasks',
				'id' => 142,
				456,
			),
		);
		$tag = array('tag1', 'tag2');
		$regId = array();
		//$regId = array('rid1', 'rid2');

		$this->push($msg, $notif, $tag, $regId);
	}

}
