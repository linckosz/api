<?php

namespace bundles\lincko\api\middlewares;

use \libs\Json;
use \config\Handler;
use \libs\Datassl;
use \bundles\lincko\api\models\Api;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Workspaces;

class CheckAccess extends \Slim\Middleware {
	
	protected $app = NULL;
	protected $data = NULL;
	protected $authorization = NULL;
	protected $authorizeAccess = false;
	protected $route = NULL;
	protected $nochecksum = false;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		if(!$this->data && $post = (object) $app->request->post()){
			if(isset($post->data) && is_string($post->data)){
				$post->data = json_decode($post->data);
			}
			$this->data = $post;
		}
		return true;
	}

	protected function autoSign(){
		$app = $this->app;
		$data = $this->data;
		$form = $data->data;
		if(isset($form->email) && isset($form->password)){
			$form->password = Datassl::decrypt($form->password, $form->email);
			if(Users::isValid($form)){
				if($user = Users::where('email', '=', mb_strtolower($form->email))->first()){
					if($user_log = UsersLog::where('username_sha1', '=', mb_strtolower($user->username_sha1))->first()){
						if($authorize = $user_log->authorize($data)){
							if(isset($authorize['public_key']) && isset($authorize['private_key'])){
								$form->password = Datassl::encrypt($form->password, $form->email);
								$this->app->lincko->securityFlash = $authorize;
								return $authorize['public_key'];
							}
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
			if($user_log = UsersLog::find($authorization->users_id)){
				if($authorize = $user_log->authorize($data)){
					if(isset($authorize['public_key']) && isset($authorize['private_key'])){
						$this->app->lincko->securityFlash = $authorize;
						return true;
					}
				}
			}
		}
		return false;
	}

	protected function setUserId(){
		$app = $this->app;
		if(isset($app->lincko->data['uid'])){
			return $app->lincko->data['uid'];
		} else if(isset($this->authorization->users_id) && $this->authorization->users_id>0){
			if($user_log = UsersLog::find($this->authorization->users_id)){
				if($user = Users::where('username_sha1', '=', $user_log->username_sha1)->first()){
					$app->lincko->data['yonghu'] = $user->username; //This variable is used for error logs only
					return $app->lincko->data['uid'] = $user->id;
				}
			}
		}
		return false;
	}

	//In case a client is using their one server to store data, we
	protected function setWorkspaceConnection($workspace){
		if($workspace->db_host!=null && $workspace->db_pwd!=null){
			$app = $this->app;
			$db_host = Datassl::simple_decrypt($workspace->db_host);
			$db_pwd = Datassl::simple_decrypt($workspace->db_pwd);
			$capsule = $app->lincko->data['capsule'];
			$capsule->addConnection(array(
				'driver' => 'mysql',
				'host' => $db_host,
				'database' => 'cli_lincko_data',
				'username' => 'cli_lincko_data',
				'password' => $db_pwd,
				'charset'   => 'utf8mb4',
				'collation' => 'utf8mb4_unicode_ci',
				'prefix' => '',
			), 'client');

			//$users = Users::on('client')->find(3);
			//\libs\Watch::php($db_host, '$db_host', __FILE__, false, false, true);
			//\libs\Watch::php($db_pwd, '$db_pwd', __FILE__, false, false, true);

		}
	}

	protected function flashKeys(){
		$app = $this->app;
		$data = $this->data;
		if(isset($data->data->set_shangzai) && $data->data->set_shangzai===true ){
			$app->lincko->securityFlash = $this->authorization;
		}
	}

	protected function checkFields(){
		$data = $this->data;
		return isset($data->api_key) && isset($data->public_key) && isset($data->checksum) && isset($data->data) && isset($data->fingerprint) && isset($data->workspace);
	}

	protected function checkAPI(){
		$app = $this->app;
		$data = $this->data;
		$api = Api::find($data->api_key);
		$app->lincko->api = $api->toArray();
		return $api;
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
		$valid = false;
		if($data->public_key === $app->lincko->security['public_key'] && in_array($this->route, $app->lincko->routeFilter)){
			//This is for any request off log, so without user ID logged in. setUserId() will return false;
			$this->authorization = new Authorization;
			$this->authorization->public_key = $app->lincko->security['public_key'];
			$this->authorization->private_key = $app->lincko->security['private_key'];
			$this->authorization->created_at = $this->authorization->updated_at = (new \DateTime)->format('Y-m-d H:i:s');
			$this->authorization->fingerprint = $data->fingerprint;
			$valid = true;
		} else if($this->authorization = Authorization::find_finger($data->public_key, $data->fingerprint)){
			$this->authorizeAccess = true;
			$valid = true;
		} else if($this->authorization = Authorization::find_finger($this->autoSign(), $data->fingerprint)){
			//Must overwrite by standard keys because the checksum has been calculated with the standard one
			$this->authorization->public_key = $app->lincko->security['public_key'];
			$this->authorization->private_key = $app->lincko->security['private_key'];
			$this->authorizeAccess = true;
			$valid = true;
		}

		if($valid){
			$this->setUserId();
			$this->flashKeys();
		}

		return $valid;
	}

	protected function checkWorkspace(){
		$app = $this->app;
		$data = $this->data;
		if($user = Users::getUser()){
			if(empty($data->workspace)){ //Shared workspace
				$app->lincko->data['workspace'] = '';
				$app->lincko->data['workspace_id'] = 0;
				$_SESSION['workspace'] = $app->lincko->data['workspace_id'];
				return true;
			} else {
				if($workspace = Workspaces::where('url', $data->workspace)->first()){
					if($workspace->checkAccess(false)){
						$app->lincko->data['workspace'] = $workspace->url;
						$app->lincko->data['workspace_id'] = $workspace->id;
						$_SESSION['workspace'] = $app->lincko->data['workspace_id'];
						$this->setWorkspaceConnection($workspace);
						return true;
					}
				}
			}
		} else if($data->public_key === $app->lincko->security['public_key']){
			//If the user and the workspace is undefined, we migth be in subscription mode, so we valid this step (it will be block later if it's not a credential operation)
			$app->lincko->data['create_user'] = true; //Authorize user account creation
			return true;
		}
		$app->lincko->data['workspace'] = '';
		$app->lincko->data['workspace_id'] = (new Workspaces)->getWorkspaceID();
		$_SESSION['workspace'] = $app->lincko->data['workspace_id'];
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
		if($app->lincko->method_suffix == '_get' || $this->nochecksum){ //We do not check checksum for files
			return true;
		}
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
		$file_error = false;
		$error = true;
		$status = 400;
		$signout = false;
		$resignin = false;

		//For cronjob to launch auto daily resume
		//Use files servers (gluster) to not slow down request on logic servers
		if($app->lincko->method_suffix == '_get' && preg_match("/^([a-z]+\.){0,1}cron\..*:(8443|8080)$/ui", $app->request->headers->Host) && preg_match("/^\/data\/resume\/hourly$/ui", $app->request->getResourceUri()) && $this->checkRoute()!==false){
			return $this->next->call();
		}

		//For file uploading, make a specific process
		if(preg_match("/^([a-z]+\.){0,1}file\..*:(8443|8080)$/ui", $app->request->headers->Host) && preg_match("/^\/file\/.+$/ui", $app->request->getResourceUri())){
			Handler::session_initialize(true);
			if($app->lincko->method_suffix == '_post' && preg_match("/^\/file\/progress\/\d+$/ui", $app->request->getResourceUri()) ){ //Video conversion
				//Security is not important here since we do not use POSt as variable to be injected somewhere
				if($this->checkRoute()!==false){
					return $this->next->call();
				}
			} else if($app->lincko->method_suffix == '_post'){ //File uploading
				$file_error = true;
				if($this->checkRoute()!==false){
					$post = $app->request->post();	
					if(
						   isset($post['shangzai_puk'])
						&& isset($post['parent_type'])
						&& isset($post['parent_id'])
						&& isset($post['workspace'])
						&& isset($post['fingerprint'])
						&& isset($post['temp_id'])
					){
						$data = new \stdClass;
						$post = $app->request->post();
						$data->public_key = Datassl::decrypt($post['shangzai_puk'], $app->lincko->security['private_key']);
						$data->api_key = 'lknscklb798w98eh9cwde8bc897q09wj';
						$data->workspace = $post['workspace'];
						$data->fingerprint = $post['fingerprint'];
						$data->checksum = 0;
						if(isset($post['http_code_ok']) && $post['http_code_ok']){
							$data->http_code_ok = (bool) $post['http_code_ok'];
						}
						$data->data = new \stdClass;
						$this->data = $data;
						$file_error = false;
						$this->nochecksum = true;
					}
				}
			} else if(
				   $app->lincko->method_suffix == '_get'
				&& preg_match("/^\/file\/\d+\/\d+\/(?:link|thumbnail|download)\/\d+\/.+$/ui", $app->request->getResourceUri())
				&& $this->checkRoute()!==false
			){ //File reading
				return $this->next->call();
			} else if($app->lincko->method_suffix == '_options'){ //Check if can upload file
				if($this->checkRoute()!==false){
					$app->lincko->http_code_ok = true;
					$json = new Json('OK', false, 200, false, false, array(), false);
					$json->render(200);
					return true;
				}
			}
		}

		//This is used to force HTTP to be ok (200)
		if(isset($data->http_code_ok) && $data->http_code_ok){
			$app->lincko->http_code_ok = (bool) $data->http_code_ok;
		}

		//Check if file access has an error
		if($file_error){
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$status = 406;

		//Check if all necessary fields in header are presents
		} else if(!$this->checkFields()){
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$status = 406;

		//Check if the route exists, we don't force to signout here
		} else if(!$this->checkRoute()) {
			$msg = $app->trans->getBRUT('api', 0, 1); //Sorry, we could not understand the request.
			$status = 404;

		//Check if the front application has the right to access to ressources via an API key
		} else if(!$this->checkAPI()) {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$signout = true;
			$status = 403;

		//Check if the public key provided matches. There is 2 kinds, one is standard with limited access to credential operations, the other is unique per device connection and correspond to a duo device(client side)/users_id(server side) and has whole access to the user workspace.
		} else if(!$this->checkPublicKey()) {
			$msg = $app->trans->getBRUT('api', 0, 2); //Please sign in.
			$status = 401;
			$resignin = true;

		//Check the workspace ID
		} else if(!$this->checkWorkspace()) {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
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
		$json->render($status);
		return false;

	}
	
}
