<?php

namespace bundles\lincko\api\middlewares;

use \libs\Json;
use \libs\Datassl;
use \libs\OneSeventySeven;
use \libs\Email;
use \bundles\lincko\api\models\Api;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\Integration;
use \bundles\lincko\api\models\Inform;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Roles;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\libs\PivotUsersRoles;
use \bundles\lincko\api\models\libs\Invitation;
use \bundles\lincko\api\models\libs\Action;
use \bundles\lincko\api\models\libs\ModelLincko;
use Illuminate\Database\Capsule\Manager as Capsule;

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
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		//Set front subdomain
		if(isset($this->data->subdomain) && !empty($this->data->subdomain)){
			$app->lincko->data['subdomain'] = $this->data->subdomain;
		}
		ModelLincko::setData($this->data);
		return true;
	}

	protected function autoSign(){
		$app = $this->app;
		$data = $this->data;
		$form = $data->data;
		$user_log = false;

		if($this->route == 'integration_connect_post'){
			$user_log = UsersLog::check_integration($data);
		}

		if(!$user_log && isset($form->log_id)){
			$log_id = Datassl::decrypt($form->log_id, 'log_id');
			$user_log = UsersLog::find($log_id); //the coloumn must be primary
		} else {
			$user_log = new UsersLog;
		}
		if($user_log){
			$authorize = $user_log->getAuthorize($data);
			if(is_array($authorize) && isset($authorize['public_key'])){
				return $authorize['public_key'];
			}
		}
		if(isset($data->public_key)){
			Authorization::clean();
			return $data->public_key;
		} else {
			return null;
		}
	}

	protected function inviteSomeone(){
		$app = $this->app;
		$data = $this->data;
		if(isset($data->user_code) && isset($app->lincko->data['uid']) && $app->lincko->data['uid']!==false){ //must be logged
			Users::inviteSomeoneCode($data);
		}
	}

	protected function checkInvitation(){
		$app = $this->app;
		$data = $this->data;
		if(isset($data->data->invitation_code) && isset($app->lincko->data['uid']) && $app->lincko->data['uid']!==false){
			$app->lincko->flash['unset_invitation_code'] = true;
			$invitation_code = $data->data->invitation_code;
			if($invitation = Invitation::withTrashed()->where('code', $invitation_code)->where('used', 0)->first()){
				$user = Users::getUser();
				$invitation_models = false;
				if(!is_null($invitation->models)){
					$invitation_models = json_decode($invitation->models);
				}
				//Record for invitation
				$invitation->guest = $user->id;
				$invitation->code = null;
				$invitation->used = true;
				$invitation->models = null;
				$invitation->email = null;
				$invitation->save();

				//Convert Invitation into users_x_users request
				$email_list = array();
				//User optional email
				if(!is_null($user->email)){
					$email_list[$user->email] = $user->email;
				}
				//Email used to invite
				if(!is_null($invitation->email)){
					$email_list[$invitation->email] = $invitation->email;
				}
				//Accout creation email
				if(isset($app->lincko->data['create_account_email']) && $app->lincko->data['create_account_email']){
					$email_list[$app->lincko->data['create_account_email']] = $app->lincko->data['create_account_email'];
				}
				
				$pivot = new \stdClass;
				$invitations = false;
				if(count($email_list)>0){
					$invitations = Invitation::withTrashed()->whereIn('email', $email_list)->where('used', 0)->get();
					foreach ($invitations as $invit) {
						if(!isset($pivot->{'users>invitation'})){
							$pivot->{'users>invitation'} = new \stdClass;
						}
						$pivot->{'users>invitation'}->{$invit->created_by} = true;
						if(!isset($pivot->{'users>access'})){
							$pivot->{'users>access'} = new \stdClass;
						}
						$pivot->{'users>access'}->{$invit->created_by} = false;
						if(!isset($pivot->{'users>models'})){
							$pivot->{'users>models'} = new \stdClass;
						}
						$pivot->{'users>models'}->{$invit->created_by} = $invit->models;
					}
				}
				
				//Authorize the user who send the invitation
				if($invitation->created_by>0 && $host = Users::find($invitation->created_by)){
					//For guest
					$pivot->{'users>access'} = new \stdClass;
					$pivot->{'users>access'}->{$host->id} = true;
					$app->lincko->data['invitation_code'] = true;
					Action::record(-9, $host->id); //Accept invitation by url code
					//If gave access to some items
					$items = new \stdClass;
					if($invitation_models){
						foreach ($invitation_models as $table => $list) {
							//Don't give access to others users
							if($table=='users'){
								continue;
							}
							$items->$table = new \stdClass;
							$pivot->{$table.'>access'} = new \stdClass;
							//Make sure that the host have access to the original item => the check is done at the invitation request from host
							if(is_numeric($list)){
								$id = intval($list);
								$pivot->{$table.'>access'}->$id = true;
								$items->$table->$id = $id;
							} else if(is_array($list) || is_object($list)){
								foreach ($list as $id) {
									$id = intval($id);
									$pivot->{$table.'>access'}->$id = true;
									$items->$table->$id = $id;
								}
							}
						}
					}
					$user->givePivotAccess(true);
					$user->pivots_format($pivot);
					$user->givePivotAccess(false);

					$pivots_var = $user->pivots_get();
					if($pivots_var && isset($pivots_var->workspaces) && is_object($pivots_var->workspaces)){
						foreach ($pivots_var->workspaces as $id => $attributes) {
							foreach ($attributes as $coloumn => $value) {
								if($coloumn == 'access' && $value){
									if($workspace = Workspaces::find($id)){
										$user->workspace = $workspace->url;
										break;
									}
								}
							}
						}
					}

					
					$user->forceSaving();
					$user->save();

					//Once the user has the rights, we refresh the permissions
					foreach ($items as $table => $list) {
						$class = Users::getClass($table);
						foreach ($list as $id) {
							if($item = $class::withTrashed()->find($id)){
								$item->forceGiveAccess();
								$item->setPerm();
							}
						}
					}
					
					$title = $app->trans->getBRUT('api', 1004, 5); //Invitation accepted
					$content_array = array(
						'mail_username' => $user->username,
					);
					$content = $app->trans->getBRUT('api', 1004, 6, $content_array); //@@mail_username~~ accepted your invitation.
					$inform = new Inform($title, $content, false, $host->getSha(), false, array());
					$inform->send();
					$users_list = array(
						$user->id => $user->id,
						$host->id => $host->id,
					);
					Inform::prepare_socket($user, $users_list);
					$partial = new \stdClass;
					$partial->users = new \stdClass;
					$partial->users->{$user->id} = false;
					Inform::socket($partial);
				}

				if($invitations){
					foreach ($invitations as $invit) {
						//Record for invitation
						$invit->guest = $user->id;
						$invit->code = null;
						$invit->used = true;
						$invit->models = null;
						$invit->email = null;
						$invit->save();
					}
				}
			}
		}
	}

	protected function setUserId(){
		$app = $this->app;
		$app->lincko->fingerprint = $this->data->fingerprint;
		if(isset($app->lincko->data['uid']) && $app->lincko->data['uid']!==false){
			return $app->lincko->data['uid'];
		} else if(isset($this->authorization->sha) && !empty($this->authorization->sha)){
			if($user = Users::where('username_sha1', $this->authorization->sha)->first()){
				$app->lincko->data['yonghu'] = $user->username; //This variable is used for error logs only
				return $app->lincko->data['uid'] = $user->id;
			}
		}
		return $app->lincko->data['uid'] = false;
	}

	//Give automatically access to a workspacce
	protected function enterWorkspace(){
		$app = $this->app;
		$data = $this->data;
		if(isset($data->data->workspace_access_code) && strlen($data->data->workspace_access_code)>0 && isset($app->lincko->data['uid']) && $app->lincko->data['uid']!==false){
			$app->lincko->flash['unset_workspace_access_code'] = true;
			if($workspace = Workspaces::Where('open', $data->data->workspace_access_code)->whereNotNull('open')->first(array('id', 'url'))){
				$pivot = new \stdClass;
				$pivot->{'workspaces>access'} = new \stdClass;
				$pivot->{'workspaces>access'}->{$workspace->id} = true;
				$user = Users::getUser();
				$user->workspace = $workspace->url;
				$user->givePivotAccess(true);
				$user->pivots_format($pivot);
				$user->givePivotAccess(false);
				$user->forceSaving();
				$user->save();
			}
		}
	}

	protected function setUserLanguage(){
		$app = $this->app;
		if(isset($app->lincko->data['uid']) && $app->lincko->data['uid']!==false){
			Users::getUser()->setLanguage();
		}
		return true;
	}

	//In case a client is using their one server to store data, we
	protected function setWorkspaceConnection($workspace) {
		if($workspace->remote){
			
			/*
				Instruction
				For the very first user, give him manually access, super user, and administrator rigths on client database.
					INSERT INTO `cli_lincko_data`.`users_x_workspaces` (`users_id`, `workspaces_id`, `access`, `super`) VALUES ('3', '4', '1', '1');
					INSERT INTO `cli_lincko_data`.`users_x_roles_x` (`id`, `deleted_at`, `users_id`, `parent_type`, `parent_id`, `access`, `roles_id`, `single`) VALUES (NULL, NULL, '3', 'workspaces', '4', '1', '1', NULL);
					=> make sure that "workspaces" has at least {"3":[2,1]} 
			*/

			$app = $this->app;
			$home = array(
				'workspaces' => $workspace,
				'users' => Users::getUser(),
			);
			$client = array(
				'users' => false,
				'workspaces' => false,
				'roles_0' => false,
				'roles_1' => false,
				'roles_2' => false,
			);

			$db_host = Datassl::decrypt_smp($workspace->db_host);
			$db_port = 3306;
			if($workspace->db_port){
				$db_port = Datassl::decrypt_smp($workspace->db_port);
			}
			$db_pwd = Datassl::decrypt_smp($workspace->db_pwd);

			$capsule = $app->lincko->data_capsule;
			$app->lincko->databases['client'] = array(
				'driver' => 'mysql',
				'host' => $db_host,
				'port' => $db_port,
				'database' => 'cli_lincko_data',
				'username' => 'cli_lincko_data',
				'password' => $db_pwd,
				'charset'   => 'utf8mb4',
				'collation' => 'utf8mb4_unicode_ci',
				'prefix' => '',
			);
			$capsule->addConnection($app->lincko->databases['client'], 'client');

			$app->lincko->databases['client']['driver'] = '******';
			$app->lincko->databases['client']['host'] = '******';
			$app->lincko->databases['client']['database'] = '******';
			$app->lincko->databases['client']['username'] = '******';
			$app->lincko->databases['client']['password'] = '******';

			//Change the default connection to third party database
			$app->lincko->data['database_data'] = 'client';

			//Check if the user has access
			$pivot = (new PivotUsers(array('workspaces')))->where('users_id', $app->lincko->data['uid'])->where('workspaces_id', $home['workspaces']->id)->where('access', 1)->first();
			//Reject access to workspace
			if(!$pivot){
				$app->lincko->data['database_data'] = 'data';
				unset($app->lincko->databases['client']);
				return $home['workspaces'];
			}

			//Check Workspaces
			$attributes = $home['workspaces']->getAttributes();
			$client['workspaces'] = Workspaces::on('client')->find($home['workspaces']->id);
			if(!$client['workspaces']){
				$attributes['db_host'] = 'hidden';
				$attributes['db_port'] = 'hidden';
				$attributes['db_pwd'] = 'hidden';
				$attributes['sftp_host'] = 'hidden';
				$attributes['sftp_port'] = 'hidden';
				$attributes['sftp_pwd'] = 'hidden';
				$client['workspaces'] = (new Workspaces)->forceFill($attributes);
				$client['workspaces']->brutSave();
			}
			Workspaces::setSFTP($attributes);

			$perm = $client['workspaces']->getPerm();
			if(!empty($perm) && $perm = json_decode($perm)){
				$need_perm = false;
				$users = $client['workspaces']->users;
				foreach ($users as $value) {
					if(!isset($perm->{$value->id})){
						$need_perm = true;
						break;
					}
				}
				if($need_perm){
					$client['workspaces']->setPerm();
				}
			}

			//Check Roles
			$roles_missing = array();
			if($roles = Roles::on('client')->whereIn('id', array(0, 1, 2))->get()){
				foreach ($roles as $key => $model) {
					$client['roles_'.$model->id] = $model;
				}
			}
			if(!$client['roles_0']){ $roles_missing[] = 0; }
			if(!$client['roles_1']){ $roles_missing[] = 1; }
			if(!$client['roles_2']){ $roles_missing[] = 2; }
			if(count($roles_missing)>0){
				$roles = Roles::on('data')->whereIn('id', array(0, 1, 2))->get();
				foreach ($roles as $key => $model) {
					$home['roles_'.$model->id] = $model;
				}
				if(!$client['roles_0']){
					$attributes = $home['roles_0']->getAttributes();
					$client['roles_0'] = (new Roles)->forceFill($attributes);
					$client['roles_0']->incrementing  = false; //Because at id=0 Eloquent auto-increment
					$client['roles_0']->brutSave();
				}
				if(!$client['roles_1']){
					$attributes = $home['roles_1']->getAttributes();
					$client['roles_1'] = (new Roles)->forceFill($attributes);
					$client['roles_1']->brutSave();
				}
				if(!$client['roles_2']){
					$attributes = $home['roles_2']->getAttributes();
					$client['roles_2'] = (new Roles)->forceFill($attributes);
					$client['roles_2']->brutSave();
				}
			}

			//Check if the user has a role defined
			$role = PivotUsersRoles::where('users_id', $app->lincko->data['uid'])->where('parent_type', 'workspaces')->where('parent_id', $home['workspaces']->id)->where('access', 1)->first();

			//Reject access to workspace
			if(!$role){
				$app->lincko->data['database_data'] = 'data';
				unset($app->lincko->databases['client']);
				return $home['workspaces'];
			}

			//Check Users
			$client['users'] = Users::on('client')->find($app->lincko->data['uid']);
			if(!$client['users']){
				$attributes = $home['users']->getAttributes();
				if(!$client['users']){
					$client['users'] = (new Users)->forceFill($attributes);
				} else {
					$client['users']->forceFill($attributes);
				}
				$client['users']->brutSave();
				Users::getUser(true); //Force to use the user item from the third party database
				//Make sure the user has a personal folder
				Projects::setPersonal();

				$client['workspaces']->setPerm();
			}
			
			Users::getUser(true); //Force to use the user item from the third party database

			$app->lincko->flash['remote'] = true;

			Workspaces::setServerPath($home['workspaces']->server_path);
			$app->lincko->data['remote'] = true;
			$workspace = $client['workspaces'];
		}
		return $workspace;
	}

	protected function checkFields(){
		$data = $this->data;
		if(!isset($data->checksum)){
			$this->nochecksum = true;
		}
		return isset($data->api_key) && isset($data->public_key) && isset($data->data) && isset($data->fingerprint) && isset($data->workspace);
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
		if(!is_null($this->route)){
			return $this->route;
		}

		$route = $app->router->getMatchedRoutes($app->request->getMethod(), $app->request->getResourceUri());
		if (is_array($route) && count($route) > 0) {
			$route = $route[0];
		}
		
		if($route){
			$this->route = $route->getName();
			return $this->route;
		}
		return false;
	}

	protected function checkPublicKey(){
		$app = $this->app;
		$data = $this->data;
		$valid = false;

		if($this->authorizeAccess && !is_null($this->authorization)){
			//Do nothing, we already agree and set object authorization
		} else if($data->public_key === $app->lincko->security['public_key'] && in_array($this->route, $app->lincko->routeFilter)){
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
			$this->authorization->private_key = $app->lincko->security['private_key'];
			$this->authorizeAccess = true;
			$valid = true;
		}	

		if($valid){
			$this->setUserId();
			$this->enterWorkspace();
			$this->setUserLanguage();
			$this->inviteSomeone();
			$this->checkInvitation();
			//Inform the browser that the third party connection is processing
			if(
				   isset($data->data)
				&& isset($data->data->integration_code)
				&& strlen($data->data->integration_code)>=1
				&& strlen($data->data->integration_code)<=10
				&& $integration = Integration::find($data->data->integration_code)
			){
				if(isset($this->authorization->sha) && $users_log = UsersLog::Where('username_sha1', $this->authorization->sha)->first(array('log'))){
					$app->lincko->flash['pukpic'] = $users_log->getPukpic();
					$app->lincko->flash['unset_integration_code'] = true;
					Integration::clean();
					$integration->processing = false;
					$integration->log = $users_log->log;
					$integration->save();
				} else {
					$integration->processing = true;
					$integration->save();
				}
			}
			if(
				   isset($data->data)
				&& isset($data->data->set_shangzai)
				&& $data->data->set_shangzai===true
				&& !isset($app->lincko->flash['pukpic'])
				&& isset($this->authorization->sha)
				&& $users_log = UsersLog::Where('username_sha1', $this->authorization->sha)->first(array('log'))
			){
				$app->lincko->flash['pukpic'] = $users_log->getPukpic();
			}
			
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
				return true;
			} else {
				if($workspace = Workspaces::where('url', $data->workspace)->first()){
					$app->lincko->data['workspace'] = $workspace->url;
					$app->lincko->data['workspace_id'] = $workspace->id;
					$app->lincko->data['workspace_default_role'] = $workspace->default_role;
					$workspace = $this->setWorkspaceConnection($workspace);
					if($workspace->checkAccess(false)){
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
				if($this->autoSign($authorization->log)){
					return true;
				}
				return false;
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
			$checksum = md5($authorization->private_key.json_encode($data->data, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE));
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

		$resourceUri = $app->request->getResourceUri();
		$route = $this->checkRoute();

		//Code integration
		if(
			    in_array($route, $app->lincko->routeSkip)
			|| ($route == 'integration_qrcode_get' && preg_match("/^([a-z]+\.){0,1}file\..*:(8443|8080)$/ui", $app->request->headers->Host))
			|| ($route == 'data_resume_hourly_get' && preg_match("/^([a-z]+\.){0,1}cron\..*:(8443|8080)$/ui", $app->request->headers->Host))
			|| ($route == 'data_unlock_get' && preg_match("/^([a-z]+\.){0,1}cron\..*:(8443|8080)$/ui", $app->request->headers->Host))
		){
			return $this->next->call();
		}

		//For users statistics
		if(
			$app->lincko->method_suffix == '_get'
			&& in_array($route, array(
					'info_action_get',
					'info_list_users_get',
					'info_weeks_get',
					'info_msg_get',
					'info_representative_get',
				))
			&& preg_match("/^([a-z]+\.){0,1}api\..*:10443$/ui", $app->request->headers->Host)
			&& $username_sha1 = UsersLog::pukpicToSha()
		){
			if($user = Users::Where('username_sha1', $username_sha1)->first(array('id'))){
				$app->lincko->data['uid'] = $user->id;
				return $this->next->call();
			}
		}

		//For file uploading, make a specific process
		if(preg_match("/^\/file\/.+$/ui", $resourceUri) && preg_match("/^([a-z]+\.){0,1}file\..*:(8443|8080)$/ui", $app->request->headers->Host)){
			$file_error = true;
			if($app->lincko->method_suffix == '_post' && preg_match("/^\/file\/progress\/\d+$/ui", $resourceUri) ){ //Video conversion
				//Security is not important here since we do not use POST as variable to be injected somewhere
				$post = $this->data;
				$w_id = $post->workspace_id;
				$app->lincko->data['uid'] = $post->uid;
				if($post->remote==false || $w_id==0){ //Shared
					return $this->next->call();
				} else if($workspace = Workspaces::where('id', $w_id)->first()){
					$app->lincko->data['workspace'] = $workspace->url;
					$app->lincko->data['workspace_id'] = $workspace->id;
					//$_SESSION['workspace'] = $app->lincko->data['workspace_id'];
					$workspace = $this->setWorkspaceConnection($workspace);
					if($workspace->checkAccess(false)){
						return $this->next->call();
					}
				}
				
			} else if($app->lincko->method_suffix == '_post'){ //File uploading
				if($route!==false){
					$post = $app->request->post();
					if(
						   isset($post['shangzai_puk'])
						&& isset($post['parent_type'])
						&& isset($post['parent_id'])
						&& isset($post['workspace'])
						&& isset($post['temp_id'])
						&& $username_sha1 = UsersLog::pukpicToSha($post['shangzai_puk'])
					){
						$data->api_key = 'lknscklb798w98eh9cwde8bc897q09wj';
						$app->lincko->data['lastvisit_enabled'] = false; //Disable lastvisit because we cannot get all items from uploading (security feature)
						$this->checkAPI();
						if(isset($post['http_code_ok']) && $post['http_code_ok']){
							$data->http_code_ok = true;
							if(is_bool($post['http_code_ok'])){
								$data->http_code_ok = $post['http_code_ok'];
							} else if($post['http_code_ok']==='true'){
								$data->http_code_ok = true;
							} else if($post['http_code_ok']==='false'){
								$data->http_code_ok = false;
							}
						}
						if($user = Users::Where('username_sha1', $username_sha1)->first(array('id'))){
							$app->lincko->data['uid'] = $user->id;
							$workspace = $post['workspace'];
							if(empty($workspace)){ //Shared
								return $this->next->call();
							} else if($workspace = Workspaces::where('url', $workspace)->first()){
								$app->lincko->data['workspace'] = $workspace->url;
								$app->lincko->data['workspace_id'] = $workspace->id;
								$workspace = $this->setWorkspaceConnection($workspace);
								return $this->next->call();
							}
						}
					}
				}
			} else if(
				   $app->lincko->method_suffix == '_get'
				&& (
					   $route == 'file_open_get' && preg_match("/^\/file\/(\d+)\/([=\d\w]+?)\/(link|thumbnail|download)\/(\d+)\/.+$/ui", $resourceUri, $matches)
					|| $route == 'file_qrcode_get' && preg_match("/^\/file\/(\d+)\/([\d\w]+?)\/(qrcode)\/(\d+)\/.+$/ui", $resourceUri, $matches)
					|| $route == 'file_profile_get' && preg_match("/^\/file\/profile\/(\d+)\/(\d+)$/ui", $resourceUri, $matches)
					|| $route == 'file_workspace_get' && preg_match("/^\/file\/workspace\/(\d+)$/ui", $resourceUri, $matches)
					|| $route == 'file_link_from_qrcode_get' && preg_match("/^\/file\/link_from_qrcode\/(\d+)\/([\d\w]+?)\/([\d\w]+?)$/ui", $resourceUri, $matches)
					|| $route == 'file_onboarding_get' && preg_match("/^\/file\/onboarding\/(\d+)\/(\d+)\.mp4$/ui", $resourceUri, $matches)
					|| $route == 'file_project_qrcode_get' && preg_match("/^\/file\/project_qrcode\/(\d+)\/(\d+)\/.+\.png$/ui", $resourceUri, $matches)
				)
				&& $route!==false
			){ //File reading
				if($username_sha1 = UsersLog::pukpicToSha()){
					$w_id = $matches[1];
					//We can skip lot of step since it's only for read, this also allow to display unavailable picture if necessary
					$app->lincko->data['uid'] = false; //Force to signin if the file is not available
					if($user = Users::Where('username_sha1', $username_sha1)->first(array('id'))){
						$app->lincko->data['uid'] = $user->id;
						if($w_id==0){ //Shared
							return $this->next->call();
						} else if($workspace = Workspaces::where('id', $w_id)->first()){
							$app->lincko->data['workspace'] = $workspace->url;
							$app->lincko->data['workspace_id'] = $workspace->id;
							$workspace = $this->setWorkspaceConnection($workspace);
							return $this->next->call();
						}
					}
				}
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
			$msg = $app->trans->getBRUT('default', 1, 4); //Sorry, we could not understand the request.
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
			//$resignin = true;
			$signout = true; //toto => Signout is not the best way, we should just return to home page (withot workspace subdomain) without signing out

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

		//Reset the default workspace to Null
		if($signout && $user = Users::getUser()){
			$user->workspace = null;
			$user->save();
		}

		$json = new Json($msg, $error, $status, $signout, $resignin);
		$json->render($status);
		return false;

	}
	
}
