<?php

namespace libs;

use \SoapClient;
use \Exception;
use \libs\STR;

class OnlineTranslator {

	const API_URL = 'http://api.microsofttranslator.com';

	protected $app = NULL;
	protected $translator = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		try {
			$this->translator = new MicrosoftTranslator(new AzureMarketplaceAuthenticator($app->lincko->translator['client_id'], $app->lincko->translator['client_secret'] ,self::API_URL));
		} catch (Exception $obj_exception) {
			\libs\Watch::php($obj_exception->getMessage(),'OnlineTranslator->__construct() error',__FILE__,true);
			return '['.$app->trans->getBRUT('default', 0, 0).']'; //The translation failed
		}
	}

	public function from($str_source_text = NULL){
		try {
			if(is_null($this->translator)){
				throw new Exception("Third part translator connection failed.");
			}
			return mb_strtolower($this->translator->detect($str_source_text));
		} catch (Exception $obj_exception) {
			\libs\Watch::php($obj_exception->getMessage(),'OnlineTranslator->from() error',__FILE__,true);
			return 'error';
		}
	}

	public function to(){
		return $this->app->trans->getClientLanguage();
	}

	public function autoTranslate($str_source_text){
		$app = $this->app;
		//Trick to keep breaking lines
		$str_source_text = STR::break_line_conv($str_source_text, '&#10;');
		try {
			if(is_null($this->translator)){
				throw new Exception("Third part translator connection failed.");
			}
			$text = $this->translator->translate($str_source_text, $this->translator->detect($str_source_text), $this->to());
			//Recover breaking lines
			$text = str_replace('&#10;', "\n", $text);
			return $text;
		} catch (Exception $obj_exception) {
			\libs\Watch::php($obj_exception->getMessage(),'OnlineTranslator->autoTranslate() error',__FILE__,true);
			return '['.$app->trans->getBRUT('default', 0, 0).']'; //The translation failed
		}
	}

}

/**
 * AzureMarketplaceAuthenticator.class.php
 * 
 * @author David Wilcock <dave.wilcock@gmail.com>
 * @version $Id: $
 */

/**
 * AzureMarketplaceAuthenticator
 *
 * Description:
 * 
 * @author David Wilcock <dave.wilcock@gmail.com>
 */
class AzureMarketplaceAuthenticator {

	/**
	 * Directory of the token file
	 */
	const TOKEN_DIRECTORY = '/tmp/';

	/**
	 * Grant type
	 */
	const GRANT_TYPE = 'client_credentials';

	/**
	 * OAuth query URL
	 */
	const QUERY_URL = 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13';

	/**
	 * The application scope
	 *
	 * @var String
	 */
	private $str_application_scope;

	/**
	 * Client ID from your application in Azure DataMarket
	 *
	 * @var String
	 */
	private $str_client_id;

	/**
	 * Client Secret from your application in Azure DataMarket
	 *
	 * @var String
	 */
	private $str_client_secret;

	/**
	 * The file where the token is stored
	 *
	 * @var String
	 */
	private $str_token_file;

	/**
	 * Both arguments are required for ANY operation, which is why they are in
	 * the constructor.
	 *
	 * @param String $str_client_id
	 * @param String $str_client_secret 
	 */
	public function __construct($str_client_id, $str_client_secret, $str_application_scope) {
		$this->str_client_id = $str_client_id;
		$this->str_client_secret = $str_client_secret;
		$this->str_application_scope = $str_application_scope;
		$this->str_token_file = self::TOKEN_DIRECTORY . sha1($this->str_client_id . $this->str_client_secret);
	}

	/**
	 * Retrieve the OAuth token to be used in Microsoft Datamarket Applications
	 *
	 * @return String
	 */
	public function get_token(){
		 if ($this->token_has_expired()) {
			$str_token = $this->request_new_token();
		} else {
			$str_token = $this->get_current_token_data();
		}
		return $str_token;
	}

	/**
	 * Has the token expired?
	 *
	 * @return Boolean 
	 */
	private function token_has_expired() {
		if (file_exists($this->str_token_file)){
			if ($this->get_current_expiry() <= time()){
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			return TRUE;
		}
	}

	/**
	 * Gets a new token, stores and returns it
	 *
	 * @return String
	 */
	private function request_new_token() {
		$obj_connection = curl_init();
		$arr_query_bits = array (
			'grant_type' => self::GRANT_TYPE,
			'scope' => $this->str_application_scope,
			'client_id' => $this->str_client_id,
			'client_secret' => $this->str_client_secret
		);
		$str_query = http_build_query($arr_query_bits);
		
		curl_setopt($obj_connection, CURLOPT_URL, self::QUERY_URL);
		curl_setopt($obj_connection, CURLOPT_HEADER, 0);
		curl_setopt($obj_connection, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($obj_connection, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($obj_connection, CURLOPT_POSTFIELDS, $str_query);
		curl_setopt($obj_connection, CURLOPT_POST, TRUE);
		curl_setopt($obj_connection, CURLOPT_TIMEOUT, 12);
		
		$str_response = curl_exec($obj_connection);
		$obj_response = json_decode($str_response);
		
		if (is_null($obj_response)){
			throw new Exception("Response wasn't VALID  - {$str_response}");
		}
		
		if (isset($obj_response->error)){
			throw new Exception($obj_response->error_description);
		}
		
		$this->store_token_response($obj_response);
		return $obj_response->access_token;
	}

	/**
	 * Reads the token file.
	 * Forces a re-read using clearstatcache for thread safety
	 *
	 * @return String
	 */
	private function read_token_file() {
		$str_token = file_get_contents($this->str_token_file);
		clearstatcache();
		return $str_token;
	}

	/**
	 * Stores the token and expiry time as a json encoded string
	 *
	 * @param StdClass $obj_response 
	 */
	private function store_token_response($obj_response){
		$arr_token_data = array(
			'expires' => time() + ($obj_response->expires_in - 10),
			'data' => $obj_response->access_token
		);
		file_put_contents($this->str_token_file, json_encode($arr_token_data));
	}

	/**
	 * Gets the token from the current file
	 *
	 * @return String 
	 */
	private function get_current_token_data(){
		$arr_token = json_decode($this->read_token_file());
		return $arr_token->data;
	}

	/**
	 * Gets the expiry time from the current file
	 *
	 * @return String
	 */
	private function get_current_expiry(){
		$arr_token = json_decode($this->read_token_file());
		return $arr_token->expires;
	}
}

/**
 * MicrosoftTranslator.class.php
 * 
 * @author David Wilcock <dave.wilcock@gmail.com>
 * @version $Id: $
 */

class MicrosoftTranslator {

	/**
	 * The WSDL URL
	 */
	const STR_WSDL_URL = 'http://api.microsofttranslator.com/V2/Soap.svc';

	/**
	 * Constructor - requires an instance of AzureMarketplaceAuthenticator, prepares
	 * a SoapClient with the client credentials
	 *
	 * @param AzureMarketplaceAuthenticator $obj_auth 
	 */
	public function __construct(AzureMarketplaceAuthenticator $obj_auth){
		$str_auth_header = "Authorization: Bearer ". $obj_auth->get_token();
		$arr_context = array(
			'http' =>array(
				'header' => $str_auth_header
			)
		);
		/**
		 * This is here because of a bug:
		 * https://bugs.php.net/bug.php?id=49853
		 */
		ini_set('user_agent', 'PHP-SOAP/' . PHP_VERSION . "\r\n" . $str_auth_header);
		$obj_context = stream_context_create($arr_context);
		$arr_options = array (
			'soap_version' => 'SOAP_1_2',
			'encoding' => 'UTF-8',
			'exceptions' => true,
			'trace' => true,
			'cache_wsdl' => 'WSDL_CACHE_NONE',
			'stream_context' => $obj_context
		);
		$this->obj_soap_connection = new SoapClient(self::STR_WSDL_URL, $arr_options);
	}

	/**
	 * Performs a translation request, returns the translated text
	 *
	 * @param string $str_source_text
	 * @param string $str_source_language
	 * @param string $str_target_language
	 * @return string
	 */
	public function translate($str_source_text = NULL, $str_source_language = NULL, $str_target_language = NULL) {
		if (is_null($str_source_text) || is_null($str_source_language) || is_null($str_target_language)){
			throw new Exception("Invalid argument");
		}
		$arr_request_arguments = array (
			'appId' => '', // no longer used, but pass it anyway
			'text' => $str_source_text,
			'from' => $str_source_language,
			'to' => $str_target_language
		);
		$obj_soap_response = $this->obj_soap_connection->Translate($arr_request_arguments);
		return $obj_soap_response->TranslateResult;
	}

	/**
	 * Performs a detection language, returns the language found
	 *
	 * @param string $str_source_text
	 * @return string
	 */
	public function detect($str_source_text = NULL) {
		if (is_null($str_source_text)){
			//throw new Exception("Invalid argument");
			return "en"; //Language translation by default
		}
		$arr_request_arguments = array (
			'appId' => '', // no longer used, but pass it anyway
			'text' => $str_source_text,
		);
		$obj_soap_detect = $this->obj_soap_connection->Detect($arr_request_arguments);
		return $obj_soap_detect->DetectResult;
	}

}
