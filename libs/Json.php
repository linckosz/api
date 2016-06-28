<?php

namespace libs;

class Json {

	protected $json = array(
		'msg' => '',
		'error' => true,
		'status' => 500,
	);

	public function __construct($msg, $error=true, $status=500, $signout=false, $resignin=false, $files=array(), $show=true){
		if(!$error){
			$this->json['error'] = false;
		}
		$this->json['status'] = intval($status);
		$this->json['msg'] = (string)$msg;

		$this->json['show'] = $show;

		//optional parameters for front end server
		if($signout || $resignin){
			$this->json['flash'] = array(
					'signout' => (bool) $signout,
					'resignin' => (bool) $resignin,
			);
		}
		if(count($files)){
			$this->json['files'] = (array)$files;
		}
		return true;
	}

	public function render($status=200){
		ob_clean();
		header("Content-type: application/json; charset=UTF-8");
		http_response_code($status);
		echo json_encode($this->json);
		return exit(0);
	}
	
}
