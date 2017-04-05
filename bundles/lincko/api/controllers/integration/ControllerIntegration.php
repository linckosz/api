<?php

namespace bundles\lincko\api\controllers\integration;

use \bundles\lincko\api\models\Integration;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\Token;
use \bundles\lincko\api\middlewares\CheckAccess;
use \libs\Controller;
use \libs\Json;
use \libs\Wechat;
use Endroid\QrCode\QrCode;
use \config\Handler;

class ControllerIntegration extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		if(!$this->data && $post = (object) $app->request->post()){
			if(isset($post->data) && is_string($post->data)){
				$post->data = json_decode($post->data);
			}
			$this->data = $post;
		}
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		return true;
	}

	protected function autoSign($log_id){
		$app = $this->app;
		$data = $this->data;
		$user_log = false;
		if($log_id){
			$user_log = UsersLog::find($log_id); //the coloumn must be primary
			if(!isset($data->data)){
				$data->data = new \stdClass;
			}
			$data->data->party = $user_log->party;
			$data->data->party_id = $user_log->party_id;
			$authorize = $user_log->getAuthorize($data);
			if(is_array($authorize) && isset($authorize['public_key'])){
				return $authorize['public_key'];
			}
		}
		return null;
	}

	public function connect_post(){
		$app = $this->app;
		if(
			   !isset($app->lincko->flash['public_key'])
			|| !isset($app->lincko->flash['pukpic'])
			|| !isset($app->lincko->flash['private_key'])
			|| !isset($app->lincko->flash['username_sha1'])
			|| !isset($app->lincko->flash['uid'])
		){
			$msg = $app->trans->getBRUT('api', 20, 1); //Software connection failed.
			$app->render(401, array('show' => true, 'msg' => $msg, 'error' => true));
		} else {
			$msg = $app->trans->getBRUT('api', 20, 2); //Software connection succeed.
			$app->render(200, array('show' => false, 'msg' => $msg));
		}
		return exit(0);
	}

	public function set_wechat_qrcode_post(){

		$app = \Slim\Slim::getInstance();
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
				Token::setToken('wechat_pub', $access_token, 3600); //toto => need to observe, it seems that the token is quickly unvalid (at least for .co)
			}
		}
		
		$jsapi_ticket = false;
		if($token = Token::getToken('wechat_jsapi_ticket')){
			$jsapi_ticket = $token->token;
		}
		if(!$jsapi_ticket){
			if($jsapi_ticket = $wechat->getJsapiTicket()){
				Token::setToken('wechat_jsapi_ticket', $jsapi_ticket, 3600);
			}
		}

		if(!$jsapi_ticket){
			unset($options['access_token']);
			$wechat = new Wechat($options);

			$access_token = $wechat->getToken();
			Token::setToken('wechat_pub', $access_token, 3600);

			$jsapi_ticket = $wechat->getJsapiTicket();
			$token = Token::setToken('wechat_jsapi_ticket', $jsapi_ticket, 3600);
		}

		Integration::clean();
		$code = rand(1, 99999);
		while(Integration::find($code)){
			usleep(10000);
			$code = rand(1, 99999);
		}

		$language = intval($app->trans->getNumber());
		if($language < 10){
			$language = '0'.$language;
		}
		$language = substr($language, 0, 2);

		$timeoffset = 0;
		if(isset($this->data->data) && isset($this->data->data->timeoffset)){
			$timeoffset = intval($this->data->data->timeoffset);
		}
		if($timeoffset < 10){
			$timeoffset = '0'.$timeoffset;
		}
		$timeoffset = substr($timeoffset, 0, 2);

		$code .= $timeoffset.$language;

		$integration = new Integration;
		$integration->code = $code;
		$integration->processing = false;
		$integration->save();

		$url = $wechat->getQRUrl($code, false, 600); //Wechat validity (10 minutes), but the limit is true so there is no expiration time

		//if URL is empty, token may not work, so we force to reset it the next call
		if(!$url){
			Token::setToken('wechat_pub', false, -1);
		}

		$app->render(200, array('show' => false, 'msg' => array(
			'msg' => 'integration code',
			'code' => $code,
			'url' => $url
		)));
		return exit(0);
	}

	public function get_wechat_token_get(){

		$app = \Slim\Slim::getInstance();
		$options = array(
			'appid' => $app->lincko->integration->wechat['public_appid'],
			'secret' => $app->lincko->integration->wechat['public_secretapp'],
		);
	
		$access_token = false;
		$expire_access_token = 0;
		if($token = Token::getToken('wechat_pub')){
			$access_token = $options['access_token'] = $token->token;
			$expire_access_token = $token->expired_at->getTimestamp();
		}
		$wechat = new Wechat($options);
		if(!$access_token){
			if($access_token = $wechat->getToken()){
				$token = Token::setToken('wechat_pub', $access_token, 3600); //toto => need to observe, it seems that the token is quickly unvalid (at least for .co)
				$expire_access_token = $token->expired_at->getTimestamp();
			}
		}

		$jsapi_ticket = false;
		$expire_jsapi_ticket = 0;
		if($token = Token::getToken('wechat_jsapi_ticket')){
			$jsapi_ticket = $token->token;
			$expire_jsapi_ticket = $token->expired_at->getTimestamp();
		}
		if(!$jsapi_ticket){
			if($jsapi_ticket = $wechat->getJsapiTicket()){
				$token = Token::setToken('wechat_jsapi_ticket', $jsapi_ticket, 3600);
				$expire_jsapi_ticket = $token->expired_at->getTimestamp();
			}
		}
			
		if(!$jsapi_ticket){
			unset($options['access_token']);
			$wechat = new Wechat($options);

			$access_token = $wechat->getToken();
			Token::setToken('wechat_pub', $access_token, 3600);
			$expire_access_token = $token->expired_at->getTimestamp();

			$jsapi_ticket = $wechat->getJsapiTicket();
			$token = Token::setToken('wechat_jsapi_ticket', $jsapi_ticket, 3600);
			$expire_jsapi_ticket = $token->expired_at->getTimestamp();
		}

		$app->render(200, array('show' => false, 'msg' => array(
			'access_token' => $access_token,
			'expire_access_token' => $expire_access_token,
			'jsapi_ticket' => $jsapi_ticket,
			'expire_jsapi_ticket' => $expire_jsapi_ticket
		)));
		return exit(0);
	}

	public function code_get(){
		$app = $this->app;
		$data = $this->data;
		$msg = $app->trans->getBRUT('api', 20, 3); //Software connection pending...
		$status = 1;

		$integration_code = false;
		if(isset($data->data) && isset($data->data->integration_code)){
			$integration_code = $data->data->integration_code;
		} else {
			Handler::session_initialize(true);
			if(isset($_SESSION['integration_code'])){
				$integration_code = $_SESSION['integration_code'];
			}
		}

		if($integration_code && isset($data->fingerprint)){
			if($integration = Integration::find($integration_code)){
				$status = 0; //[0]failed [1]pending [2]processing [3]done
				$msg = $app->trans->getBRUT('api', 20, 1); //Software connection failed.
				if(is_null($integration->log)){
					$msg = $app->trans->getBRUT('api', 20, 3); //Software connection pending...
					$status = 1;
					if($integration->processing){
						$msg = $app->trans->getBRUT('api', 20, 4); //Software connection processing...
						$status = 2;
					}
				} else if(Authorization::find_finger($this->autoSign($integration->log), $data->fingerprint)){
					$msg = $app->trans->getBRUT('api', 20, 2); //Software connection succeed.
					$status = 3;
				}
			}
		}
		
		$app->render(200, array('show' => false, 'msg' => array('msg' => $msg, 'status' => $status)));
		return exit(0);
	}

	public function setcode_get(){
		$app = $this->app;
		Integration::clean();
		$code = substr(md5(uniqid()), 0, 8);
		while(Integration::find($code)){
			usleep(10000);
			$code = substr(md5(uniqid()), 0, 8);
		}

		$integration = new Integration;
		$integration->code = $code;
		$integration->processing = false;
		$integration->save();
		
		Handler::session_initialize(true);
		$_SESSION['integration_code'] = $code;

		$app->lincko->flash['integration_code'] = $code;

		$app->render(200, array('show' => false, 'msg' => array('msg' => 'integration code', 'code' => $code)));
		return exit(0);
	}

	public function qrcode_get($mini=false){
		$app = $this->app;
		Integration::clean();
		ob_clean();
		flush();
		$code = substr(md5(uniqid()), 0, 8);
		while(Integration::find($code)){
			usleep(10000);
			$code = substr(md5(uniqid()), 0, 8);
		}

		$integration = new Integration;
		$integration->code = $code;
		$integration->processing = false;
		$integration->save();
		
		Handler::session_initialize(true);
		$_SESSION['integration_code'] = $code;

		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_HOST'].'/integration/code/'.$code;
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

		//https://packagist.org/packages/endroid/qrcode
		$qrCode = new QrCode();

		if($mini && $mini_path = $app->lincko->path.'/bundles/lincko/api/public/images/generic/integration/'.$mini.'.png'){
			if(is_file($mini_path)){
				$qrCode
					->setLogo($mini_path)
					->setLogoSize(144)
				;
			}
		}

		$qrCode
			->setText($url)
			->setSize(800)
			->setPadding(40)
			->setErrorCorrection('medium')
			//->setForegroundColor(array('r' => 251, 'g' => 160, 'b' => 38, 'a' => 0)) //Orange is not working very well
			->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
			->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
			->setImageType(QrCode::IMAGE_TYPE_PNG)
		;
		header('Content-Type: '.$qrCode->getContentType());
		$qrCode->render();
		
		return exit(0);
	}

}
