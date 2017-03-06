<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Folders;
use \libs\Datassl;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\libs\Action;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\data\Workspaces;
use Carbon\Carbon;
use GeoIp2\Database\Reader;

class ControllerInfo extends Controller {

	protected $app = NULL;
	protected $data = NULL;
	protected static $first_week_day = 1; //Start Monday

	protected $email_exclude = array(
		'willshakespeare@mac.com',
		'jimbilek@mac.com',
		'jimsweibo@gmail.com',
		'brunoocto@gmail.com',
		'hyunwoo126@gmail.com',
		'hyunwoo126@qq.com',
	);

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	protected static function setFirstWeekDay($day){
		return self::$first_week_day = intval($day) % 7;
	}

	protected static function getWeekOffset(){
		return (8 - self::$first_week_day) % 7;
	}

	public function beginning_post(){
		$app = $this->app;
		$path = $app->lincko->filePathPrefix.$app->lincko->filePath.'/'.$app->lincko->data['uid'].'/screenshot/';
		$folder = new Folders;
		$folder->createPath($path);
		if(isset($this->data->data) && isset($this->data->data->canvas) && $image = explode('base64,',$this->data->data->canvas)){
			if(isset($image[1])){
				file_put_contents($path.time(), base64_decode($image[1]));
			}
		}
		$app->render(200, array('msg' => 'ok',));
		return true;
	}

	public function action_post(){
		$app = $this->app;
		if(isset($this->data->data) && isset($this->data->data->action)){
			$action = $this->data->data->action;
			if(is_numeric($action)){
				//Always use negative for outside value, Positives value are used to follow history
				if($action>0){
					$action = -$action;
				}
				$info = null;
				if(isset($this->data->data->info)){
					$info = $this->data->data->info;
				}
				Action::record($action, $info);
			}
		}
		$app->render(200, array('msg' => 'ok',));
		return true;
	}

	public function action_get($users_id){
		$app = $this->app;
		if(!Users::amIadmin()){
			return false;
		}
		ob_clean();
		flush();
		Workspaces::getSFTP();
		$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');

		$from = $to = Carbon::now()->format('Y-m-d');
		$this->list_users_get($from, $to, $users_id);

		echo '<div style="font-family:monospace;font-size: 13px;">';

		echo '
		<style>
			.info {
				color: #B6CCBC;
			}
		</style>
		';

		if($user = Users::find($users_id)){
			echo $user->username."<br />\n".'['.$users_id.']'."<br />\n";
			if(!empty($user->email)){
				echo $user->email."<br />\n";
			}
			echo "<br />\n";
			if(!empty($user->profile_pic)){
				$link = 'https://'.$app->lincko->data['lincko_back'].'file.'.$app->lincko->domain.':8443/file/profile/'.$app->lincko->data['workspace_id'].'/'.$user->id.'?'.$user->profile_pic;
				echo "<img style='height:100px;' src='$link' /><br />\n";
				echo "<br />\n";
			}

			$history_action = array();

			if($items = Action::Where('users_id', $users_id)->get()){
				foreach ($items as $item) {
					$history_action[$item->created_at][] = array($item->action, $item->info);
				}
			}

			$user_timestamp = $user->created_at->getTimestamp();
			$models = Data::getModels();
			foreach ($models as $table => $class) {
				$archive = $class::getArchive();
				$columns = $class::getColumns();
				if($archive && isset($archive['created_at']) && in_array('created_by', $class::getColumns()) && in_array('created_at', $class::getColumns())){
					if($table=='users'){
						$history_action[$user_timestamp][] = array($archive['created_at'][1], null);
					} else if($items = $class::withTrashed()->Where('created_by', $users_id)->get(array('created_at'))){
						foreach ($items as $item) {
							$timestamp = $item->created_at->getTimestamp();
							if($timestamp < $user_timestamp || $timestamp >= $user_timestamp + 20){ //We give a gap of 16s to cover clone dates
								$history_action[$timestamp][] = array($archive['created_at'][1], null);
							}
						}
					}
				}
			}

			ksort($history_action);

			$prev_day = false;
			$prev_timestamp = false;
			$lap = 0;
			foreach ($history_action as $timestamp => $list) {
				$day = date('M d, Y', $timestamp);
				$time = date('H : i : s', $timestamp);
				if($day != $prev_day){
					echo "<br />\n".$day."<br />\n";
					$prev_day = $day;
				}
				if(!$prev_timestamp){
					$prev_timestamp = $timestamp;
				}
				$gap = $timestamp - $prev_timestamp;
				$prev_timestamp = $timestamp;
				if($gap<=0){
					$gap = '&nbsp;&nbsp;0';
				} else if($gap<10){
					$gap = '&nbsp;&nbsp;'.$gap;
				} else if($gap<100){
					$gap = '&nbsp;'.$gap;
				} else if($gap>=1000){
					$gap = '+++';
				}
				foreach ($list as $action) {
					if(!empty($action[1])){
						echo $time.' ['.$gap.'] => '.Action::action($action[0]).' <span class="info">'.$action[1]."</span><br />\n";
					} else {
						echo $time.' ['.$gap.'] => '.Action::action($action[0])."<br />\n";
					}	
					$gap = '&nbsp;&nbsp;0';
				}
			}
			
		}
		echo '</div>';
		echo "<br />\n<br />\n<br />\n";
		return exit(0);
	}

	public function representative_get($sales_id){
		$app = $this->app;
		self::setFirstWeekDay(6); //Start week on Satuday
		$week_offset = self::getWeekOffset();
		ob_clean();
		flush();
		$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');
		echo '<div style="font-family:monospace;font-size: 13px;">';

		echo '
		<style>
			table {
				border: none;
				border-spacing: 0;
				border-collapse: collapse;
				padding: 4px;
				white-space: nowrap;
			}
			tr, td {
				border: solid 1px;
				border-spacing: 0;
				border-collapse: collapse;
				padding: 4px 8px;
			}
			.perc {
				float: left;
				color:#979797;
				padding-right: 20px;
			}
		</style>
		';

		//Decode sales ID
		$sales_id = Datassl::decrypt($sales_id);

		echo "<br />\n";
		echo 'Representative ID: '.$sales_id;
		echo "<br />\n";

		$date_gap = array();
		$sales_qty = array();

		$min = false;
		$max = false;
		if($actions = Action::Where('action', -15)->where('info', $sales_id)->get(array('created_at'))){
			foreach ($actions as $action) {
				$action->created_at;
				if(!$min || $action->created_at < $min){
					$min = $action->created_at;
				}
				if(!$max || $action->created_at > $max){
					$max = $action->created_at;
				}
			}
		}

		if(!$actions || !$min || !$max){
			echo 'No record.';
		} else {
			//Make sure that no week are missing
			$min = Carbon::createFromTimestamp($min);
			$week = $min->copy();
			$max = Carbon::createFromTimestamp($max);
			while($week <= $max){
				$week_start = $week->copy()->startOfWeek()->subDay($week_offset);
				$week_end = $week->copy()->endOfWeek()->subDay($week_offset);
				if($week->format('N') >= self::$first_week_day){
					//Insure to work with next week value
					$week_start->addDay(7);
					$week_end->addDay(7);
				}
				if($week_start < $min){
					$week_start = $min->copy()->startOfDay();
				}
				
				$format_year = $date->format('\\yy');
				$format_week = intval($date->format('W'));
				if($format_week<10){
					$format_week = '0'.$format_week;
				}
				$year_week = $format_year.'-w'.$format_week;

				$sales_qty[$year_week] = 0;

				$date_gap[$year_week] = array($week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
				$week->addDay(1); //Must be dily to cover week offset
			}

			foreach ($actions as $action) {
				$created_at = Carbon::createFromTimestamp($action->created_at);
				$format_year = $created_at->format('\\yy');
				$format_week = intval($created_at->format('W'));
				if($format_week<10){
					$format_week = '0'.$format_week;
				}
				$year_week = $format_year.'-w'.$format_week;
				if(isset($sales_qty[$year_week])){
					$sales_qty[$year_week]++;
				}
			}
		}

		echo '<table>';

		//Reorganize the data to be draw
		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Year - Week</td>';
		foreach ($date_gap as $week => $array) {
			echo '<td>'.$week.'</td>';
		}
		echo '<td style="font-weight: bold;">Total</td>';
		echo '</tr>';

		$total = 0;
		$prev_value = false;
		echo '<tr style="text-align:right;">';
		echo '<td style="text-align:left;">Quantity</td>';
		foreach ($date_gap as $week => $array) {
			if(isset($sales_qty[$week]) && !empty($sales_qty[$week])){
				$growth = '';
				if($prev_value!==false && $sales_qty[$week]>0){
					$growth = '+'.number_format(floor(($sales_qty[$week] / $prev_value) * 100));
					$growth = '<span class="perc">('.$growth.'%) </span>';
				}
				echo '<td>'.$growth.$sales_qty[$week].'</td>';
				$prev_value = $sales_qty[$week];
				$total += intval($sales_qty[$week]);
			} else {
				echo '<td></td>';
				$prev_value = false;
			}
		}
		echo '<td style="font-weight: bold;">'.$total.'</td>';
		echo '</tr>';

		echo '</div>';
		echo "<br />\n<br />\n<br />\n";
		return exit(0);
	}

	public function list_users_get($from, $to, $users_id=false){
		$app = $this->app;
		if(!Users::amIadmin()){
			return false;
		}
		set_time_limit(3600); //Set to 1 hour workload at the most
		proc_nice(10);
		ob_clean();
		flush();
		Workspaces::getSFTP();
		if(!$users_id){
			$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');
		}
		echo '<div style="font-family:monospace;font-size: 13px;">';
		echo '
		<style>
			table {
				border: none;
				border-spacing: 0;
				border-collapse: collapse;
				padding: 4px;
				white-space: nowrap;
			}
			tr, td {
				border: solid 1px;
				border-spacing: 0;
				border-collapse: collapse;
				padding: 4px 8px;
			}
		</style>
		';

		if(!$users_id){
			echo 'Convert Table to CSV:';
			echo "<br />\n";
			echo 'https://chrome.google.com/webstore/search/html%20table%20to%20csv?_category=extensions';
			echo "<br />\n<br />\n<br />\n";
		} else {
			echo "<br />\n";
		}

		$from = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
		$to = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
		
		if($from >= $to){
			$to = $from->copy()->endOfDay(); //24H the same day
		}
		
		$users = array();
		echo '<table>';

		function insert_info(){
			echo '
			<tr style="text-align:center;background-color:#F6FFF6;color:#979797">
				<td>item</td>
				<td>Profile</td>
				<td>id</td>
				<td>Lincked to</td>
				<td>Username</td>
				<td>Firstname</td>
				<td>Lastname</td>
				<td>Creation</td>
				<td>Nbr logs</td>
				<td>Last log</td>
				<td>Gap days</td>
				<td>Country</td>
				<td>City</td>
				<td>OS</td>
				<td>Device</td>
				<td>Platform</td>
				<td>Gender</td>
				<td>Language</td>
				<td>email</td>
				<td>Methods</td>
				<td>Projects</td>
				<td>Tasks</td>
				<td>Notes</td>
				<td>Files</td>
				<td>Comments</td>
				<td>Messages</td>
			</tr>
			';
		}

		$users_logs = UsersLog::WhereNotNull('username_sha1')->get(array('username_sha1'));
		$sha_exists = array();
		foreach ($users_logs as $user_logs) {
			if(!empty($user_logs->username_sha1)){
				$sha_exists[$user_logs->username_sha1] = array();
			}
		}
		unset($users_logs);

		insert_info();
		$insert = 0;
		$total = 0;
		$models = array('projects', 'tasks', 'notes', 'files', 'comments', 'messages');
		if(!$users_id){
			$users = Users::WhereNotNull('username_sha1')->whereBetween('created_at', [$from, $to])->get();
		} else {
			$users = Users::where('id', $users_id)->get();
		}
		if($users){
			//Record action by users
			$list_users = array();
			$create_limit = array();
			$last_activity = array();
			$last_os = array();
			$last_device = array();
			$last_platform = array();
			$last_location = array();
			$nbr_logs = array();
			$qty = array();
			foreach ($users as $user) {
				if($user->id<=1){ //System users
					continue;
				}
				if($app->lincko->domain=='lincko.com'){
					if(in_array($user->email, $this->email_exclude) || preg_match('/.+@lincko\..+/ui', $user->email) || preg_match('/.+@eyeis\.com/ui', $user->email)){
						continue;
					}
				}
				$list_users[$user->id] = $user->id;
				$qty[$user->id] = array();
				$create_limit[$user->id] = $user->created_at->getTimestamp(); 
				$last_activity[$user->id] = $user->created_at->getTimestamp();
				$last_os[$user->id] = '';
				$last_device[$user->id] = '';
				$last_platform[$user->id] = '';
				$last_location[$user->id] = false;
				$nbr_logs[$user->id] = 0;
				//Initialization at zero
				foreach ($models as $table) {
					$qty[$user->id][$table] = 0;
				}
			}

			foreach ($models as $table) {
				if($class = Users::getClass($table)){
					if($items = $class::withTrashed()->WhereIn('created_by', $list_users)->get(array('id', 'created_by', 'created_at'))){
						foreach ($items as $item) {
							if(!isset($list_users[$item->created_by])){
								continue;
							}
							$timestamp = $item->created_at->getTimestamp();
							if($timestamp < $create_limit[$item->created_by] || $timestamp >= $create_limit[$item->created_by] + 20){//We give a gap of 20s to cover clone dates
								$qty[$item->created_by][$table]++;
							}
							if($last_activity[$item->created_by] < $timestamp){
								$last_activity[$item->created_by] = $timestamp;
							}
						}
					}
				}
			}

			if($actions = Action::WhereIn('users_id', $list_users)->get()){
				foreach ($actions as $action) {
					if(!isset($list_users[$action->users_id])){
						continue;
					}
					if($action[$action->users_id] < $action->created_at){
						$action[$action->users_id] = $action->created_at;
					}
					if($action->action==-1 && !empty($action->info)){
						$info = json_decode($action->info, true);
						if(is_array($info)){
							if(isset($info[0])){
								$last_os[$action->users_id] = $info[0];
							}
							if(isset($info[1])){
								$last_device[$action->users_id] = $info[1];
							}
							if(isset($info[2])){
								$last_platform[$action->users_id] = $info[2];
							}
							if(isset($info[3]) && filter_var($info[3], FILTER_VALIDATE_IP)){
								$last_location[$action->users_id] = $info[3];
							}
						}
					}
					if($action->action==-1){
						$nbr_logs[$action->users_id]++;
					}
					if($last_activity[$item->created_by] < $action->created_at){
						$last_activity[$item->created_by] = $action->created_at;
					}
				}
			}

			$geoip_reader = new Reader($app->lincko->path.'/bundles/lincko/api/models/geoip2/GeoLite2-City.mmdb');

			$now = time();

			//Draw the table
			foreach ($users as $user) {
				if(!isset($list_users[$user->id]) || !isset($sha_exists[$user->username_sha1])){
					continue;
				}
				$parties = UsersLog::Where('username_sha1', $user->username_sha1)->get(array('party'));
				if($parties->count()==0){
					//Do not display if the user is attached to no login method
					continue;
				}
				$total++;
				echo '<tr>';
				echo '<td>#'.$total.'</td>';
				$link = 'https://'.$app->lincko->data['lincko_back'].'file.'.$app->lincko->domain.':8443/file/profile/'.$app->lincko->data['workspace_id'].'/'.$user->id.'?'.intval($user->profile_pic);
				echo '<td style="text-align:center;"><img style="height:32px;cursor:pointer;" src="'.$link.'" onclick="window.open(\'https://'.$app->lincko->data['lincko_back'].'api.'.$app->lincko->domain.':10443/info/action/'.$user->id.'\', \'_top\');" /></td>';
				echo '<td>'.$user->id.'</td>';
				if(is_numeric($user->linked_to)){
					echo '<td style="cursor:pointer;background-color:#FFFAE6;text-align:center;" onclick="window.open(\'https://'.$app->lincko->data['lincko_back'].'api.'.$app->lincko->domain.':10443/info/action/'.$user->linked_to.'\', \'_top\');" >'.$user->linked_to.'</td>';
				} else {
					echo '<td></td>';
				}
				echo '<td>'.$user->username.'</td>';
				echo '<td>'.$user->firstname.'</td>';
				echo '<td>'.$user->lastname.'</td>';
				echo '<td>'.$user->created_at.'</td>';
				$logs = '';
				if($nbr_logs[$user->id]>0){
					$logs = number_format($nbr_logs[$user->id]);
				}
				echo '<td>'.$logs.'</td>';
				echo '<td>'.Carbon::createFromTimestamp($last_activity[$user->id]).'</td>';
				$gap = $now - $last_activity[$user->id];
				$gap = max(0, floor($gap/86400)); //Number of days since last connection
				if($gap<=0){
					$gap = '';
				} else {
					$gap = number_format($gap);
				}
				echo '<td>'.$gap.'</td>';
				//Geo Location
				$country = '';
				$city = '';
				if(isset($last_location[$user->id]) && filter_var($last_location[$user->id], FILTER_VALIDATE_IP)){
					try {
						$geoip_record = $geoip_reader->city($last_location[$user->id]);
						$country = $geoip_record->country->name;
						$city = $geoip_record->city->name;
					} catch (\Exception $e) {
						$country = '';
						$city = '';
					}
				}
				echo '<td>'.$country.'</td>';
				echo '<td>'.$city.'</td>';
				$os = '';
				if(isset($last_os[$user->id])){
					$os = $last_os[$user->id];
				}
				echo '<td>'.$os.'</td>';
				$device = '';
				if(isset($last_device[$user->id])){
					$device = $last_device[$user->id];
				}
				echo '<td>'.$device.'</td>';
				$platform = '';
				if(isset($last_platform[$user->id])){
					$platform = $last_platform[$user->id];
				}
				echo '<td>'.$platform.'</td>';
				$gender = 'M';
				if($user->gender){
					$gender = 'F';
				}
				echo '<td>'.$gender.'</td>';
				echo '<td>'.$user->language.'</td>';
				echo '<td>'.$user->email.'</td>';
				$list_party = '';
				foreach ($parties as $party) {
					$list_party_name = false;
					if(empty($party->party)){
						$list_party_name = 'email';
					} else if(in_array($party->party, array('wechat_pub', 'wechat_dev'))){ //Exclude some
						$list_party_name = 'wechat';
					} else {
						$list_party_name = $party->party;
					}
					if($list_party_name && !isset($sha_exists[$user->username_sha1][$list_party_name])){
						$list_party .= $list_party_name.', ';
						$sha_exists[$user->username_sha1][$list_party_name] = true;
					}
				}
				//Delete the last comma
				if(strpos(strrev($list_party), ' ,')===0){
					$list_party = rtrim($list_party, ', ');
				}
				echo '<td>'.$list_party.'</td>';
				foreach ($models as $table) {
					$count = $qty[$user->id][$table];
					if($count<=0){
						$count = '';
					}
					echo '<td>'.$count.'</td>';
				}

				$insert++;
				if($insert>=20){
					insert_info();
					$insert = 0;
				}
				echo '</tr>';
			}
			
		}
		echo '</table>';

		echo '</div>';
		proc_nice(0);
		if(!$users_id){
			echo "<br />\n<br />\n<br />\n";
			return exit(0);
		}
		echo "<br />\n";
		return true;
	}



	public function weeks_get(){
		$app = $this->app;
		if(!Users::amIadmin()){
			return false;
		}
		set_time_limit(3600); //Set to 1 hour workload at the most
		proc_nice(10);
		ob_clean();
		flush();
		Workspaces::getSFTP();
		self::setFirstWeekDay(6); //Start week on Satuday
		$week_offset = self::getWeekOffset();
		$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');
		echo '<div style="font-family:monospace;font-size: 13px;">';

		echo '
		<style>
			table {
				border: none;
				border-spacing: 0;
				border-collapse: collapse;
				padding: 4px;
				white-space: nowrap;
			}
			tr, td {
				border: solid 1px;
				border-spacing: 0;
				border-collapse: collapse;
				padding: 4px 8px;
			}
			.perc {
				float: left;
				color:#979797;
				padding-right: 20px;
			}
		</style>
		';

		$date_gap = array();
		$new_accounts = array();
		$integrations = array();
		$countries = array();
		$info_os = array();
		$info_os_fields = array();
		$info_device = array();
		$info_device_fields = array();
		$info_platform = array();
		$info_platform_fields = array();
		$sales = array();
		$sales_fields = array();
		$countries_fields = array();
		$accounts_fields = array('email', 'wechat', 'facebook', 'gmail');
		$integrations_fields = array();
		$today = Carbon::now()->endOfDay();

		$row_titles = array(' ');


		$announcement = Carbon::createFromFormat('Y-m-d', '2017-02-08')->endOfDay();
		$announcement_str = 'Pre A (2017-02-08)';
		$new_accounts[$announcement_str] = array();
		$integrations[$announcement_str] = array();
		$countries[$announcement_str] = array();
		$sales[$announcement_str] = array();
		$info_os[$announcement_str] = array();
		$info_device[$announcement_str] = array();
		$info_platform[$announcement_str] = array();

		$date_gap[$announcement_str] = array('2000-01-01', '2017-02-08');

		//Make sure that no week are missing
		$week = $announcement->copy()->addDay(1);
		while($week <= $today){
			$week_start = $week->copy()->startOfWeek()->subDay($week_offset);
			$week_end = $week->copy()->endOfWeek()->subDay($week_offset);
			if($week->format('N') >= self::$first_week_day){
				//Insure to work with next week value
				$week_start->addDay(7);
				$week_end->addDay(7);
			}
			if($week_start < $announcement){
				$week_start = $announcement->copy()->addDay(1)->startOfDay();
			}

			$format_year = $week_end->format('\\yy');
			$format_week = intval($week_end->format('W'));
			if($format_week<10){
				$format_week = '0'.$format_week;
			}
			$year_week = $format_year.'-w'.$format_week;

			$new_accounts[$year_week] = array();
			$integrations[$year_week] = array();
			$countries[$year_week] = array();
			$sales[$year_week] = array();
			$info_os[$year_week] = array();
			$info_device[$year_week] = array();
			$info_platform[$year_week] = array();

			$date_gap[$year_week] = array($week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
			$week->addDay(1); //Must be dily to cover week offset
		}

		$users = Users::whereNotNull('username_sha1')->get(array('username_sha1'));
		$sha_exists = array();
		foreach ($users as $user) {
			if(!empty($user->username_sha1)){
				$sha_exists[$user->username_sha1] = array();
			}
		}
		unset($users);
		
		if($parties = UsersLog::orderBy('created_at', 'asc')->get(array('created_at', 'party', 'party_id', 'username_sha1'))){
			foreach ($parties as $party) {
				$list_party = false;
				if(!isset($sha_exists[$party->username_sha1])){
					continue;
				} else if(is_null($party->party)){
					$list_party = 'email';
				} else if(!empty($party->party) && !in_array($party->party, array('wechat_pub', 'wechat_dev'))){
					$list_party = 'wechat';
				} else {
					continue;
				}
				if($app->lincko->domain=='lincko.com'){
					if(in_array($party->party_id, $this->email_exclude) || preg_match('/.+@lincko\..+/ui', $party->party_id) || preg_match('/.+@eyeis\.com/ui', $party->party_id)){
						continue;
					}
				}
				if(isset($sha_exists[$party->username_sha1][$list_party])){
					//do not display twice the same login method
					continue;
				}
				$sha_exists[$party->username_sha1][$list_party] = true; //Avoid to double the same user, the orderby helps to crab the first user creation account
				$year_week = $announcement_str;
				if($party->created_at > $announcement){
					if($party->created_at->format('N') >= self::$first_week_day){
						$format_year = $party->created_at->copy()->addDay($week_offset)->format('\\yy');
						$format_week = intval($party->created_at->copy()->addDay($week_offset)->format('W'));
					} else {
						$format_year = $party->created_at->format('\\yy');
						$format_week = intval($party->created_at->format('W'));
					}
					if($format_week<10){
						$format_week = '0'.$format_week;
					}
					$year_week = $format_year.'-w'.$format_week;
				}
				if(!isset($new_accounts[$year_week][$list_party])){
					$new_accounts[$year_week][$list_party] = 1;
				} else {
					$new_accounts[$year_week][$list_party]++;
				}
				if(!in_array($list_party, $accounts_fields)){
					$accounts_fields[] = $list_party;
				}
			}
		}

		$geoip_reader = new Reader($app->lincko->path.'/bundles/lincko/api/models/geoip2/GeoLite2-Country.mmdb');
		$convert = Action::getConvert();
		if($actions = Action::WhereIn('action', [-1, -12, -13, -15])->get()){
			foreach ($actions as $action) {
				$date = Carbon::createFromTimestamp($action->created_at);
				$year_week = $announcement_str;
				if($date > $announcement){
					if($date->format('N') >= self::$first_week_day){
						$format_year = $date->copy()->addDay($week_offset)->format('\\yy');
						$format_week = intval($date->copy()->addDay($week_offset)->format('W'));
					} else {
						$format_year = $date->format('\\yy');
						$format_week = intval($date->format('W'));
					}
					if($format_week<10){
						$format_week = '0'.$format_week;
					}
					$year_week = $format_year.'-w'.$format_week;
				}
				if($action->action==-1 && !empty($action->info)){
					try {
						$info = json_decode($action->info, true);
						if(is_array($info)){
							if(isset($info[0])){
								if(!isset($info_os[$year_week][$info[0]])){
									$info_os[$year_week][$info[0]] = 1;
								} else {
									$info_os[$year_week][$info[0]]++;
								}
								if(!in_array($info[0], $info_os_fields)){
									$info_os_fields[] = $info[0];
								}
							}
							if(isset($info[1])){
								if(!isset($info_device[$year_week][$info[1]])){
									$info_device[$year_week][$info[1]] = 1;
								} else {
									$info_device[$year_week][$info[1]]++;
								}
								if(!in_array($info[1], $info_device_fields)){
									$info_device_fields[] = $info[1];
								}
							}
							if(isset($info[2])){
								if(!isset($info_platform[$year_week][$info[2]])){
									$info_platform[$year_week][$info[2]] = 1;
								} else {
									$info_platform[$year_week][$info[2]]++;
								}
								if(!in_array($info[2], $info_platforms_fields)){
									$info_platform_fields[] = $info[2];
								}
							}
							if(isset($info[3]) && filter_var($info[3], FILTER_VALIDATE_IP)){
								$geoip_record = $geoip_reader->country($info[3]);
								$country = $geoip_record->country->name;
								if(!isset($countries[$year_week][$country])){
									$countries[$year_week][$country] = 1;
								} else {
									$countries[$year_week][$country]++;
								}
								if(!in_array($country, $countries_fields)){
									$countries_fields[] = $country;
								}
							}
						}
					} catch (\Exception $e) {}
				} else if($action->action==-15){
					if(!empty($action->info)){
						if(!isset($sales[$year_week][$action->info])){
							$sales[$year_week][$action->info] = 1;
						} else {
							$sales[$year_week][$action->info]++;
						}
						if(!in_array($action->info, $sales_fields)){
							$sales_fields[] = $action->info;
						}
					}
				} else if(isset($convert[$action->action])){
					if(!isset($integrations[$year_week][$convert[$action->action]])){
						$integrations[$year_week][$convert[$action->action]] = 1;
					} else {
						$integrations[$year_week][$convert[$action->action]]++;
					}
					if(!in_array($convert[$action->action], $integrations_fields)){
						$integrations_fields[] = $convert[$action->action];
					}
				}
			}
		}

		echo 'Convert Table to CSV:';
		echo "<br />\n";
		echo 'https://chrome.google.com/webstore/search/html%20table%20to%20csv?_category=extensions';
		echo "<br />\n<br />\n";
		echo '1 week = From Sat 00:00 To Fri 23:59';
		echo "<br />\n<br />\n<br />\n";
		
		echo '<table>';

		//Reorganize the data to be draw
		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Method</td>';
		foreach ($date_gap as $week => $array) {
			echo '<td
				style="cursor:pointer;"
				onclick="window.open(\'https://'.$app->lincko->data['lincko_back'].'api.'.$app->lincko->domain.':10443/info/list_users/'.$array[0].'/'.$array[1].'/\', \'_top\');"
			>'.$week.'</td>';
		}
		echo '<td style="font-weight: bold;">Total</td>';
		echo '</tr>';
		foreach ($accounts_fields as $title) {
			$total = 0;
			$prev_value = false;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($new_accounts as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					$growth = '';
					if($prev_value!==false && $array[$title]>0){
						$growth = '+'.number_format(floor(($array[$title] / $prev_value) * 100));
						$growth = '<span class="perc">('.$growth.'%) </span>';
					}
					echo '<td>'.$growth.$array[$title].'</td>';
					$prev_value = $array[$title];
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
					$prev_value = false;
				}
			}
			echo '<td style="font-weight: bold;">'.$total.'</td>';
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Integration</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '<td></td>';
		echo '</tr>';
		ksort($integrations_fields);
		foreach ($integrations_fields as $title) {
			$total = 0;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($integrations as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.number_format($array[$title]).'</td>';
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
				}
			}
			echo '<td style="font-weight: bold;">'.number_format($total).'</td>';
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Location logs</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '<td></td>';
		echo '</tr>';
		ksort($countries_fields);
		foreach ($countries_fields as $title) {
			$total = 0;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($countries as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.number_format($array[$title]).'</td>';
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
				}
			}
			echo '<td style="font-weight: bold;">'.number_format($total).'</td>';
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Operation Systems logs</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '<td></td>';
		echo '</tr>';
		ksort($info_os_fields);
		foreach ($info_os_fields as $title) {
			$total = 0;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($info_os as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.number_format($array[$title]).'</td>';
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
				}
			}
			echo '<td style="font-weight: bold;">'.number_format($total).'</td>';
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Devices logs</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '<td></td>';
		echo '</tr>';
		ksort($info_device_fields);
		foreach ($info_device_fields as $title) {
			$total = 0;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($info_device as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.number_format($array[$title]).'</td>';
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
				}
			}
			echo '<td style="font-weight: bold;">'.number_format($total).'</td>';
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Platforms logs</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '<td></td>';
		echo '</tr>';
		ksort($info_platform_fields);
		foreach ($info_platform_fields as $title) {
			$total = 0;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($info_platform as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.number_format($array[$title]).'</td>';
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
				}
			}
			echo '<td style="font-weight: bold;">'.number_format($total).'</td>';
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Representatives perf</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '<td></td>';
		echo '</tr>';
		ksort($sales_fields);
		foreach ($sales_fields as $title) {
			$total = 0;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.' ('.Datassl::encrypt($title).')</td>';
			foreach ($sales as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.number_format($array[$title]).'</td>';
					$total += intval($array[$title]);
				} else {
					echo '<td></td>';
				}
			}
			echo '<td style="font-weight: bold;">'.number_format($total).'</td>';
			echo '</tr>';
		}

		echo '</table>';

		echo '</div>';
		echo "<br />\n<br />\n<br />\n";
		proc_nice(0);
		return exit(0);
	}

}
