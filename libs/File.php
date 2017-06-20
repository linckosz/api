<?php

namespace libs;

class File {

	protected $file = false;

	public function __construct($file = false){
		return $this->file = $file;
	}

	public function getFile(){
		return $this->file;
	}

	public static function getLocalFile($file){
		if(is_file($_SERVER['DOCUMENT_ROOT'].$file)){
			return $_SERVER['DOCUMENT_ROOT'].$file;
		}
		return false;
	}

	public static function getLatest($file){
		//For static files, we do no need to use a subdomain to disable the cache because those files will be loaded from browser cache (code 200) directly,
		//they don't need to ask the server (slower respond time, code 304), use a subdomain through ajax always return a 304 code.
		if(is_file($_SERVER['DOCUMENT_ROOT'].$file)){
			return $file.'?'.filemtime($_SERVER['DOCUMENT_ROOT'].$file);
		} else {
			return $file.'?'.time();
		}
	}

	public static function getGroupLatest($name, $files, $content=false){
		if(empty($name) || empty($files)){
			return '';
		}
		if(!is_array($files) && is_string($files)){
			$files = array($files);
		}
		if(is_array($files)){
			$time = 0;
			foreach ($files as $file) {
				if(is_file($_SERVER['DOCUMENT_ROOT'].$file)){
					$filemtime = filemtime($_SERVER['DOCUMENT_ROOT'].$file);
					if($filemtime > $time){
						$time = $filemtime;
					}
				}
			}
			$create = true;
			$fast = '/lincko/_group/'.$name;
			if(!is_file($_SERVER['DOCUMENT_ROOT'].$fast || $time > filemtime($_SERVER['DOCUMENT_ROOT'].$fast))){
				$folder = new Folders;
				$folder->createPath($_SERVER['DOCUMENT_ROOT'].'/lincko/_group', 0770);
				$str = '';
				foreach ($files as $file) {
					$str .= file_get_contents($_SERVER['DOCUMENT_ROOT'].$file).PHP_EOL;
				}
				$output = fopen($_SERVER['DOCUMENT_ROOT'].$fast, 'w');
				fwrite($output, $str);
				fclose($output);
			}
			if($content){
				return $str;
			}
			return $fast.'?'.filemtime($_SERVER['DOCUMENT_ROOT'].$fast);
		}
		return '';
	}

}
