<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Folders;
use Carbon\Carbon;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\libs\Action;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\data\Workspaces;
use GeoIp2\Database\Reader;

class ControllerInfo extends Controller {

	protected $app = NULL;
	protected $data = NULL;

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
				if($action==-1){ //Logged
					$info = $this->data->myip; //User IP is sent by the Frontend PHP, so the information is not available as a JS one
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
		echo '<div style="font-family:monospace;font-size: 13px;">';
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

			if($items = Action::Where('users_id', $users_id)->get(array('created_at', 'action'))){
				foreach ($items as $item) {
					$history_action[$item->created_at][] = $item->action;
				}
			}

			$user_timestamp = $user->created_at->getTimestamp();
			$models = Data::getModels();
			foreach ($models as $table => $class) {
				$archive = $class::getArchive();
				$columns = $class::getColumns();
				if($archive && isset($archive['created_at']) && in_array('created_by', $class::getColumns()) && in_array('created_at', $class::getColumns())){
					if($table=='users'){
						$history_action[$user_timestamp][] = $archive['created_at'][1];
					} else if($items = $class::withTrashed()->Where('created_by', $users_id)->get(array('created_at'))){
						foreach ($items as $item) {
							$timestamp = $item->created_at->getTimestamp();
							if($timestamp < $user_timestamp || $timestamp >= $user_timestamp + 20){ //We give a gap of 16s to cover clone dates
								$history_action[$timestamp][] = $archive['created_at'][1];
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
					echo $time.' ['.$gap.'] => '.Action::action($action)."<br />\n";
					$gap = '&nbsp;&nbsp;0';
				}
			}
			
		}
		echo '</div>';
		echo "<br />\n<br />\n<br />\n";
		return exit(0);
	}

	public function list_users_get($from, $to){
		$app = $this->app;
		if(!Users::amIadmin()){
			return false;
		}
		set_time_limit(3600); //Set to 1 hour workload at the most
		proc_nice(10);
		ob_clean();
		flush();
		Workspaces::getSFTP();
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
		</style>
		';

		$from = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
		$to = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
		
		if($from >= $to){
			$to = $from->copy()->endOfDay(); //24H the same day
		}

		echo 'Convert Table to CSV:';
		echo "<br />\n";
		echo 'https://chrome.google.com/webstore/search/html%20table%20to%20csv?_category=extensions';
		echo "<br />\n<br />\n<br />\n";
		
		$users = array();
		echo '<table>';

		function insert_info(){
			echo '
			<tr style="text-align:center;background-color:#F6FFF6;color:#979797">
				<td>item</td>
				<td>Profile</td>
				<td>id</td>
				<td>Username</td>
				<td>Firstname</td>
				<td>Lastname</td>
				<td>Creation</td>
				<td>Nbr logs</td>
				<td>Last log</td>
				<td>Gap days</td>
				<td>Country</td>
				<td>City</td>
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

		insert_info();
		$insert = 0;
		$total = 0;
		$models = array('projects', 'tasks', 'notes', 'files', 'comments', 'messages');
		$users = Users::whereBetween('created_at', [$from, $to])->get();
		if($users){
			//Record action by users
			$list_users = array();
			$create_limit = array();
			$last_activity = array();
			$last_location = array();
			$nbr_logs = array();
			$qty = array();
			foreach ($users as $user) {
				if(in_array($user->email, $this->email_exclude) || ($app->lincko->domain=='lincko.com' && preg_match('/.+@lincko\..+/ui', $user->email))){
					continue;
				}
				$list_users[$user->id] = $user->id;
				$qty[$user->id] = array();
				$create_limit[$user->id] = $user->created_at->getTimestamp(); 
				$last_activity[$user->id] = $user->created_at->getTimestamp();
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
					if($action[$action->users_id] < $action->created_at){
						$action[$action->users_id] = $action->created_at;
					}
					if($action->action==-1 && !empty($action->info) && filter_var($action->info, FILTER_VALIDATE_IP)){
						$last_location[$user->id] = $action->info;
					}
					if($action->action==-1){
						$nbr_logs[$user->id]++;
					}
				}
			}

			$geoip_reader = new Reader($app->lincko->path.'/bundles/lincko/api/models/geoip2/GeoLite2-City.mmdb');

			//Draw the table
			foreach ($users as $user) {
				if(!isset($list_users[$user->id])){
					continue;
				}
				$total++;
				echo '<tr>';
				echo '<td>#'.$total.'</td>';
				$link = 'https://'.$app->lincko->data['lincko_back'].'file.'.$app->lincko->domain.':8443/file/profile/'.$app->lincko->data['workspace_id'].'/'.$user->id.'?'.intval($user->profile_pic);
				echo '<td style="text-align:center;"><img style="height:32px;cursor:pointer;" src="'.$link.'" onclick="window.open(\'https://'.$app->lincko->data['lincko_back'].'api.'.$app->lincko->domain.':10443/info/action/'.$user->id.'\', \'_top\');" /></td>';
				echo '<td>'.$user->id.'</td>';
				echo '<td>'.$user->username.'</td>';
				echo '<td>'.$user->firstname.'</td>';
				echo '<td>'.$user->lastname.'</td>';
				echo '<td>'.$user->created_at.'</td>';
				$logs = '';
				if($nbr_logs[$user->id]>0){
					$logs = $nbr_logs[$user->id];
				}
				echo '<td>'.$logs.'</td>';
				echo '<td>'.Carbon::createFromTimestamp($last_activity[$user->id]).'</td>';
				$gap = $last_activity[$user->id] - $create_limit[$user->id];
				$gap = max(0, floor($gap/86400)); //Number of days
				if($gap<=0){
					$gap = '';
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
				$gender = 'M';
				if($user->gender){
					$gender = 'F';
				}
				echo '<td>'.$gender.'</td>';
				echo '<td>'.$user->language.'</td>';
				echo '<td>'.$user->email.'</td>';
				$list_party = '';
				if($parties = UsersLog::Where('username_sha1', $user->username_sha1)->get(array('party'))){
					foreach ($parties as $party) {
						if(empty($party->party)){
							$list_party .= 'email, ';
						} else if(!in_array($party->party, array('wechat_pub', 'wechat_dev'))){
							$list_party .= $party->party.', ';
						}
					}
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
		echo "<br />\n<br />\n<br />\n";
		proc_nice(0);
		return exit(0);
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

		$date_gap[$announcement_str] = array('2000-01-01', '2017-02-08');

		//Make sure that no week are missing
		$week = $announcement->copy()->addDay(1);
		while($week <= $today){
			$format = $week->format('\\yy-\\wW');
			$new_accounts[$format] = array();
			$integrations[$format] = array();
			$countries[$format] = array();

			$week_start = $week->copy()->startOfWeek();
			if($week_start < $announcement){
				$week_start = $announcement->copy()->addDay(1)->startOfDay();
			}
			$week_end = $week->copy()->endOfWeek();

			$date_gap[$format] = array($week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
			$week->addDay(1);
		}
		
		if($parties = UsersLog::all(array('created_at', 'party'))){
			foreach ($parties as $party) {
				$list_party = false;
				if(is_null($party->party)){
					$list_party = 'email';
				} else if(!empty($party->party) && !in_array($party->party, array('wechat_pub', 'wechat_dev'))){
					$list_party = $party->party;
				} else {
					continue;
				}
				$year_week = $announcement_str;
				if($party->created_at > $announcement){
					$year_week = $party->created_at->format('\\yy-\\wW');
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
		if($actions = Action::WhereIn('action', [-1, -12, -13])->get()){
			foreach ($actions as $action) {
				$date = Carbon::createFromTimestamp($action->created_at);
				$year_week = $announcement_str;
				if($date > $announcement){
					$year_week = $date->format('\\yy-\\wW');
				}
				if($action->action==-1 && !empty($action->info) && filter_var($action->info, FILTER_VALIDATE_IP)){
					try {
						$geoip_record = $geoip_reader->country($action->info);
						$country = $geoip_record->country->name;
						if(!isset($countries[$year_week][$country])){
							$countries[$year_week][$country] = 1;
						} else {
							$countries[$year_week][$country]++;
						}
						if(!in_array($country, $countries_fields)){
							$countries_fields[] = $country;
						}
					} catch (\Exception $e) {}
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
		echo '</tr>';

		foreach ($accounts_fields as $title) {
			$prev_value = false;
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($new_accounts as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					$growth = '';
					if($prev_value!==false){
						$growth = floor((1 - $prev_value / $array[$title]) * 100);
						if($growth>0){
							$growth = '+'.$growth;
						}
						$growth = '<span class="perc">('.$growth.'%)</span>';
					}
					echo '<td>'.$growth.$array[$title].'</td>';
					$prev_value = $array[$title];
				} else {
					echo '<td></td>';
					$prev_value = false;
				}
			}
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Integration</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '</tr>';

		ksort($integrations_fields);
		foreach ($integrations_fields as $title) {
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;"'.$title.'</td>';
			foreach ($integrations as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.$array[$title].'</td>';
				} else {
					echo '<td></td>';
				}
			}
			echo '</tr>';
		}

		echo '<tr style="text-align:center;background-color:#F6FFF6;color:#979797">';
		echo '<td style="text-align:left;">Location</td>';
		foreach ($new_accounts as $week => $array) {
			echo '<td></td>';
		}
		echo '</tr>';

		ksort($countries_fields);
		foreach ($countries_fields as $title) {
			echo '<tr style="text-align:right;">';
			echo '<td style="text-align:left;">'.$title.'</td>';
			foreach ($countries as $week => $array) {
				if(isset($array[$title]) && !empty($array[$title])){
					echo '<td>'.$array[$title].'</td>';
				} else {
					echo '<td></td>';
				}
			}
			echo '</tr>';
		}

		echo '</table>';

		echo '</div>';
		echo "<br />\n<br />\n<br />\n";
		proc_nice(0);
		return exit(0);
	}

}
