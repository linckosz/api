<?php

namespace bundles\lincko\api\middlewares;

use \libs\Json;
use \libs\Datassl;
use \bundles\lincko\api\models\Api;
use \bundles\lincko\api\models\Users;
use \bundles\lincko\api\models\Authorization;

class CheckAccess extends \Slim\Middleware {
	
	protected $app = NULL;
	protected $data = NULL;
	protected $authorization = NULL;
	protected $authorizeAccess = false;
	protected $route = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	protected function autoSign(){
		$app = $this->app;
		$data = $this->data;
		$form = $data->data;

		if(isset($form->email) && isset($form->password)){
			$form->password = Datassl::decrypt($form->password, $form->email);
			if(Users::isValid('user_signin',$form)){
				if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
					if($authorize = $user->authorize($data)){
						if(isset($authorize['public_key']) && isset($authorize['private_key'])){
							$form->password = Datassl::encrypt($form->password, $form->email);
							$this->app->lincko->securityFlash = $authorize;
							return $authorize['public_key'];
						}
					}
				}
			}
			$form->password = Datassl::encrypt($form->password, $form->email);
		}
		return $this->data->public_key;
	}

	protected function reSignIn(){
		$app = $this->app;
		$data = $this->data;
		$authorization = $this->authorization;

		if($authorization){
			if($user = Users::find($authorization->user_id)){
				if($authorize = $user->authorize($data)){
					if(isset($authorize['public_key']) && isset($authorize['private_key'])){
						$this->app->lincko->securityFlash = $authorize;
						return true;
					}
				}
			}
		}
		return false;
	}

	protected function checkFields(){
		$data = $this->data;
		return isset($data->api_key) && isset($data->public_key) && isset($data->checksum) && isset($data->data);
	}

	protected function checkAPI(){
		$app = $this->app;
		$data = $this->data;
		return Api::find($data->api_key);
	}

	protected function checkRoute(){
		$app = $this->app;

		$route = $app->router->getMatchedRoutes($app->request->getMethod(), $app->request->getResourceUri());
		if (is_array($route) && count($route) > 0) {
			$route = $route[0];
		}
		
		if($route){
			$this->route = $route->getName();
			return true;
		}
		return false;
	}

	protected function checkPublicKey(){
		$app = $this->app;
		$data = $this->data;
		if($data->public_key === $app->lincko->security['public_key'] && in_array($this->route, $app->lincko->routeFilter)){
			$this->authorization = new Authorization;
			$this->authorization->public_key = $app->lincko->security['public_key'];
			$this->authorization->private_key = $app->lincko->security['private_key'];
			$this->authorization->created_at = $this->authorization->updated_at = (new \DateTime)->format('Y-m-d H:i:s');
			return true;
		} else if($this->authorization = Authorization::find($data->public_key)){
			$this->authorizeAccess = true;
			return true;
		} else if($this->authorization = Authorization::find($this->autoSign())){
			//Must overwrite by standard keys because the checksum has been calculated with the standard one
			$this->authorization->public_key = $app->lincko->security['public_key'];
			$this->authorization->private_key = $app->lincko->security['private_key'];
			$this->authorizeAccess = true;
			return true;
		}

		return false;
	}

	protected function checkRouteAccess(){
		$app = $this->app;

		if(!$this->authorizeAccess && !in_array($this->route, $app->lincko->routeFilter)){
			return false;
		}
		return true;
	}

	protected function checkExpired(){
		$app = $this->app;
		$data = $this->data;
		$authorization = $this->authorization;

		if($authorization){
			//Do not check expiration session for logging functions
			if(in_array($this->route, $app->lincko->routeFilter)){
				return true;
			}
			$expired = new \DateTime($authorization->updated_at);
			$expired->add(new \DateInterval('PT'.$app->lincko->security['expired'].'S'));
			$now = new \DateTime();
			if($expired < $now){
				return $this->reSignIn();
			}
			return true;
		}
		return false;
	}

	protected function checkSum(){
		$app = $this->app;
		$data = $this->data;
		$authorization = $this->authorization;
		if($authorization){
			$checksum = md5($authorization->private_key.json_encode($data->data));
			return $checksum === $data->checksum;
		}
		return false;
	}

	public function call() {
		$app = $this->app;
		$data = $this->data;

		$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$error = true;
		$status = 400;
		$signout = true;
		$resignin = false;

		//Check if all necessary fields in header are presents
		if(!$this->checkFields()){
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$status = 406;

		//Check if the route exists, we don't force to signout here
		} else if(!$this->checkRoute()) {
			$msg = $app->trans->getBRUT('api', 0, 1); //Sorry, we could not understand the request.
			$status = 404;
			$signout = false;

		//Check if the front application has the right to access to ressources via an API key
		} else if(!$this->checkAPI()) {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$status = 403;

		//Check if the public key provided matchs. There is 2 kinds, one is standard with limited access to credential operations, the other is unique per device connection and correspond to a duo device(client side)/user_id(server side) and has whole access to the user workspace.
		} else if(!$this->checkPublicKey()) {
			$msg = $app->trans->getBRUT('api', 0, 2); //Please sign in.
			$status = 401;
			$resignin = true;

		//Check if the route is available for standard public key (limited to credential operations only)
		} else if(!$this->checkRouteAccess()) {
			$msg = $app->trans->getBRUT('api', 0, 2); //Please sign in.
			$status = 401;

		//Check if the current connection has expired 
		} else if(!$this->checkExpired()) {
			$msg = $app->trans->getBRUT('api', 0, 3); //Your session has expired, please sign in again.
			$status = 440;
			$resignin = true;

		//Check with a private key (not transmitted in header) if the checksum of the fields values is identical (avoid any value modification hack during transmission)
		} else if(!$this->checkSum()) {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$status = 401;
			$resignin = true;

		} else {
			return $this->next->call();
		}

		$json = new Json($msg, $error, $status, $signout, $resignin);
		$json->render();
		return false;

	}
	
}