<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\Token;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Notif;
use \bundles\lincko\api\models\data\Users;
use \libs\Email;
use \libs\Wechat;
use Carbon\Carbon;

class Inform {

	protected $methods = array(
		'email' => false,
		'mobile' => false,
		'wechat' => false,
	);

	protected $title = '[Lincko]';

	protected $content_html = 'Lincko';

	protected $content_text = 'Lincko';

	protected $annex = '';

	protected $item = false;

	protected $sha = array();

	public function __construct($title, $content, $annex=false, $sha, $item=false, array $include=array(), array $exclude=array()){
		$app = ModelLincko::getApp();
		if(gettype($sha)=='string'){
			$sha = array($sha);
		} else if(gettype($sha)!='array'){
			\libs\Watch::php($sha, 'Inform: Wrong $sha format', __FILE__, __LINE__, true);
			return false;
		}
		if(empty($annex)){
			$annex = '';
		}
		if(in_array('wechat_pub', $include) || in_array('wechat_dev', $include)){
			$include[] = 'wechat';
		}
		if(empty($include)){ //All type of messages if not specified
			foreach ($this->methods as $key => $value) {
				$this->methods[$key] = true;
			}
		} else {
			foreach ($include as $key) {
				if(isset($this->methods[$key])){
					$this->methods[$key] = true;
				}
			}
		}
		if(!empty($exclude)){
			foreach ($exclude as $key) {
				if(isset($this->methods[$key])){
					$this->methods[$key] = false;
				}
			}
		}
		$this->title = $title;
		$this->content_html = $content;
		$this->content_text = (new \Html2Text\Html2Text($content))->getText();
		$this->annex = $annex;
		$this->item = $item;
		//Grab Username
		$usernames = array();
		if($users = Users::WhereIn('username_sha1', $sha)->get(array('username_sha1', 'username', 'email'))){
			foreach ($users as $user) {
				$usernames[$user->username_sha1] = $user->username;
				//Add sub-email if exists
				if(!empty($user->email) && $this->methods['email']){
					$this->sha['email'][$user->email] = $user->username; //In makes sens only if the user specified a secondary email (Users and UsersLog do not have the same email)
				}
			}
		}
		//Get all communication methods
		if($users = UsersLog::WhereIn('username_sha1', $sha)->where('notify', 1)->get(array('username_sha1', 'party', 'party_id'))){
			foreach ($users as $user) {
				if(isset($usernames[$user->username_sha1])){
					$party = $user->party;
					$party_id = $user->party_id;
					if(empty($party)){
						$party = 'email';
					} else if($party=='wechat_pub'){
						$party = 'wechat';
						$party_id = str_replace('oid.pub.', '', $user->party_id);
					} else if($party=='wechat' || $party=='wechat_dev'){
						continue;
					}
					if($this->methods['mobile']){
						$this->sha['mobile'][$user->username_sha1] = $usernames[$user->username_sha1];
					}
					if(isset($this->methods[$party]) && $this->methods[$party]){
						$this->sha[$party][$party_id] = $usernames[$user->username_sha1];
					}
				}
			}
		}
		return true;
	}

	/*
		If we want to add some receivers manually, please use this format
		$manual = array(
			'email' => array(
				'sample@lincko.com' => 'username',
			),
		);
	*/
	public function send($manual=false){
		if(is_array($manual)){
			foreach ($manual as $party => $list) {
				if(isset($this->methods[$party]) && $this->methods[$party] && is_array($list)){
					foreach ($list as $value) {
						$this->sha[$party][$party_id] = $party_id;
					}
				}
			}
		}
		foreach ($this->methods as $key => $value) {
			if($value){
				$fn = 'send_'.$key;
				if(method_exists(get_called_class(), $fn)){
					$this->$fn();
				}
			}
		}
	}

	protected function send_wechat(){
		$app = ModelLincko::getApp();
		if(!isset($this->sha['wechat'])){
			return false;
		}
		$options = array(
			'appid' => $app->lincko->integration->wechat['public_appid'],
			'secret' => $app->lincko->integration->wechat['public_secretapp'],
		);
		
		if($access_token = Token::getToken('wechat_pub')){
			$options['access_token'] = $access_token;
		}
		$wechat = new Wechat($options);
		if(!$access_token){
			$wechat->getToken();
		}

		$content = '[ '.$this->title." ]\n".$this->content_text;

		foreach ($this->sha['wechat'] as $openid => $username) {
			$wechat->sendMsg($openid, $content, 'text');
		}
		
		return $wechat;
	}

	protected function send_email(){
		$app = ModelLincko::getApp();
		if(!isset($this->sha['email'])){
			return false;
		}
		$mail_template_array = array(
			'mail_head' => $this->title,
			'mail_body' => $this->content_html,
			'mail_foot' => $this->annex,
		);
		$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);
		$mail = new Email();
		foreach ($this->sha['email'] as $email => $username) {
			if(ModelLincko::validEmail($email)){
				$mail->addAddress($email, $username);
				$mail->setSubject($this->title);
				$mail->sendLater($mail_template);
			}
		}
	}

	protected function send_mobile(){
		if(!isset($this->sha['mobile'])){
			return false;
		}
		(new Notif)->push($this->title, $this->content_text, $this->item, $this->sha['mobile']);
	}

}
