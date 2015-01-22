<?php

namespace libs;

class Datassl {

	const SALT = '7dujjbs&%9b=#nk!2v8v*1|1';
	const METHOD = 'AES-256-CBC';

	protected static function createIV($password){
		$ivlen = openssl_cipher_iv_length(self::METHOD);
		$iv = md5(base64_encode($password));
		while(mb_strlen($iv) < $ivlen){
			$iv .= md5($iv);
		}
		return mb_substr($iv, 0, $ivlen);
	}

	/**
	* Encrypt string using openSSL module
	* @param string $textToEncrypt
	* @param string $password User's optional password
	*/
	public static function encrypt($textToEncrypt, $password = ''){
		return base64_encode(openssl_encrypt($textToEncrypt, self::METHOD, self::SALT, OPENSSL_RAW_DATA, self::createIV($password)));
	}

	/**
	* Decrypt string using openSSL module
	* @param string $textToDecrypt
	* @param string $password User's optional password
	*/
	public static function decrypt($textToDecrypt, $password = ''){
		return openssl_decrypt(base64_decode($textToDecrypt), self::METHOD, self::SALT, OPENSSL_RAW_DATA, self::createIV($password));
	}
}
