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

	public function send($msg, $notif=array(), $aliases=array(), $tag=array()){
		$client = self::$client;
		$response = $client->push();
		$response->setPlatform('all');
		if(!empty($aliases)){
			$response->addAlias($aliases);
		} else {
			//just do nothing if no any alias
			return true;
		}

		$response->iosNotification($msg, $notif);
		$response->androidNotification($msg, $notif);
		//Setup default for winPhone
		if(!isset($notif['title'])){ $notif['title'] = null; }
		if(!isset($notif['_open_page'])){ $notif['_open_page'] = null; }
		if(!isset($notif['extras'])){ $notif['extras'] = null; }
		$response->addWinPhoneNotification($msg, $notif['title'], $notif['_open_page'], $notif['extras']);
		try {
			$result = $response->send();
			return $result;
		} catch (\JPush\Exceptions\JPushException $e) {
			//\libs\Watch::php('$e', 'JPushException', __FILE__, __LINE__, true); //error
			return false;
		}
	}

	public function push($title, $msg, $item=false, $aliases=array()){
		$title = (new \Html2Text\Html2Text($title))->getText();
		$msg = (new \Html2Text\Html2Text($msg))->getText();
		$notif = array(
			'sound' => $title,
			'title' => $title,
			'badge' => '+1',
			'build_id' => 2,
			'content-available' => true,
			'mutable-available' => true,
			'_open_page' => 'winPage',
		);
		if($item){
			$domain = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_HOST'];
			$url = 'javascript:app_generic_state.openItem(false, \''.$domain.'/#'.$item->getTable().'-'.$item->id.'\');';
			$notif['extras'] = array(
				'url' => $url,
			);
		}
		return $this->send($msg, $notif, $aliases);
	}

}
