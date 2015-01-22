<?php

namespace libs;

class Json {

	protected $json = array(
		'msg' => '',
		'error' => true,
		'status' => 500,
		'signout' => true,
	);

	public function __construct($msg, $error=true, $status=500, $signout=false, $resignin=false){
		if(!$error){
			$this->json['error'] = false;
		}
		$this->json['status'] = intval($status);
		$this->json['msg'] = (string)$msg;
		$this->json['flash'] = array(
				'signout' => (boolean)$signout,
				'resignin' => (boolean)$resignin,
		);
		return true;
	}

	public function render(){
		header("Content-type: application/json; charset=UTF-8");
		echo $result = json_encode($this->json);
		return exit(0);
	}
	
}
