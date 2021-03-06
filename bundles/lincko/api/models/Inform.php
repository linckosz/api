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

	protected $username_sha1 = array();

	protected static $list_socket = array();

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
					$this->username_sha1[$user->username_sha1] = $user->username_sha1;
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
						$this->sha['mobile'][$user->username_sha1] = $user->username_sha1;
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
					foreach ($list as $party_id => $username) {
						$this->sha[$party][$party_id] = $username;
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

	//Quicker Response to user (websocket)
	//The sending is postpone to the end od Data.php to make sure the object is well formatted (which can avoid some JS unstabilities)
	public static function prepare_socket($item, $users_list){
		$app = ModelLincko::getApp();
		if($item){
			$table = $item->getTable();
			if(!isset(self::$list_socket[$table])){
				self::$list_socket[$table] = array();
			}
			if(!isset(self::$list_socket[$table][$item->id])){
				self::$list_socket[$table][$item->id] = array();
			}
			foreach ($users_list as $users_id => $list) {
				self::$list_socket[$table][$item->id][$users_id] = $users_id;
			}
		}
		return true;
	}

	//We use a pointer as parameter to speed up the process and limit memory used, just make sure we don't modify $partial here, just read it
	public static function socket(&$partial, $force=false){
		$app = ModelLincko::getApp();
		foreach (self::$list_socket as $table => $table_list) {
			if(isset($partial->$table)){
				foreach ($table_list as $id => $array) {
					if(isset($partial->$table->$id)){
						$users_list = $array;
						$users_list_kicked = array();
						//Make sure we include also new members because prepare is using all stake
						if(isset($partial->$table->$id->_perm) && is_object($partial->$table->$id->_perm)){
							//Do not send item to the users kicked, but force to launch getlest
							foreach ($users_list as $users_id => $value) {
								if(!isset($partial->$table->$id->_perm->$users_id)){
									$users_list_kicked[$users_id] = $users_id;
								}
							}
							$users_list = array();
							foreach ($partial->$table->$id->_perm as $users_id => $value) {
								$users_list[$users_id] = $users_id;
							}
						}

						$item = new \stdClass;
						if($partial->$table->$id && is_object($partial->$table->$id)){
							if($table!='users'){ //Exclude Users because of invitation process issue
								$item->$table = new \stdClass;
								$item->$table->$id = $partial->$table->$id;
							}
						}
						$msg = new \stdClass;
						$msg->show = false;
						$msg->error = false;
						$msg->status = 200;
						$msg->msg = $item;
						$msg->info = 'getlatest';
						if($force){
							$msg->info = 'forcelatest';
						}

						if(!empty($users_list)){
							$data = new \stdClass;
							$data->uid = $users_list;
							$data->msgToFront = $msg;
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, 'http://' . $app->lincko->socket . '/');
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_FORCE_OBJECT));
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
							curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=UTF-8')); 
							curl_exec($ch);   
							curl_close($ch);
						}

						if(!empty($users_list_kicked)){
							$msg->msg = false;
							$data = new \stdClass;
							$data->uid = $users_list_kicked;
							$data->msgToFront = $msg;
							$ch = curl_init();
							curl_setopt($ch, CURLOPT_URL, 'http://' . $app->lincko->socket . '/');
							curl_setopt($ch, CURLOPT_POST, true);
							curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_FORCE_OBJECT));
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
							curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=UTF-8')); 
							curl_exec($ch);   
							curl_close($ch);
						}
					}
				}
			}
		}
	}

	protected function send_wechat(){
		return true; //Do not allow wechat notification, it poluate the pblic account too much
		$app = ModelLincko::getApp();
		if(!isset($this->sha['wechat'])){
			return false;
		}
		$options = array(
			'appid' => $app->lincko->integration->wechat['public_appid'],
			'secret' => $app->lincko->integration->wechat['public_secretapp'],
		);
		
		$access_token = false;
		if($token = Token::getToken('wechat_pub')){
			$access_token = $options['access_token'] = $token->token;
		}
		$wechat = new Wechat($options);
		if(!$access_token){
			if($access_token = $wechat->getToken()){
				Token::setToken('wechat_pub', $access_token, 3600);
			}
		}
		
		//Add item link if any
		if($this->item){
			$content = '[ '.$this->title." ]\n"
				.$app->lincko->data['subdomain'].$app->lincko->domain.'/#'.$this->item->getTable().'-'.$this->item->id."\n"
				.$this->content_text;
		} else {
			$content = '[ '.$this->title." ]\n".$this->content_text;
		}

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
