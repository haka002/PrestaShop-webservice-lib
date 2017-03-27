<?php

namespace PrestaShopWebservice;

use PrestaShopWebservice\Exception\WebserviceException;

class Webservice
{
	/** @var string Shop URL */
	protected $url;

	/** @var string Authentification key */
	protected $key;

	/** @var boolean Is debug activated */
	protected $debug;

	/** @var string PS version */
	protected $version;

	/** Mandatory parameters without URL option */
	// Define the parameters with an url with query params. In this case the other options are not playing.
	const OPTION_URL           = 'url';
	// Or you have to use resource
	const OPTION_RESOURCE = 'resource';
	/** The available options for the api. */
	const OPTION_ID            = 'id';
	const OPTION_FILTER        = 'filter';
	const OPTION_DISPLAY       = 'display';
	const OPTION_SORT          = 'sort';
	const OPTION_LIMIT         = 'limit';
	const OPTION_ID_SHOP       = 'id_shop';
	const OPTION_ID_GROUP_SHOP = 'id_group_shop';
	const OPTION_OUTPUT_FORMAT = 'output_format';

	// Here are some value constans
	const OPTION_VALUE_OUTPUT_FORMAT_JSON = 'JSON';
	const OPTION_VALUE_OUTPUT_FORMAT_XML = 'xml';

	/** Version constants for version validation */
	const PS_COMPATIBLE_VERSIONS_MIN = '1.4.0.0';
	const PS_COMPATIBLE_VERSIONS_MAX = '1.7.99.99';

	/**
	 * PrestaShopWebservice constructor. Throw an exception when CURL is not installed/activated
	 * <code>
	 * <?php
	 * require_once('./PrestaShopWebservice.php');
	 * try
	 * {
	 * 	$ws = new PrestaShopWebservice('http://mystore.com/', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ', false);
	 * 	// Now we have a webservice object to play with
	 * }
	 * catch (WebserviceException $ex)
	 * {
	 * 	echo 'Error : '.$ex->getMessage();
	 * }
	 * ?>
	 * </code>
	 * @param string $url Root URL for the shop
	 * @param string $key Authentification key
	 * @param mixed $debug Debug mode Activated (true) or deactivated (false)
	*/
	function __construct($url, $key, $debug = true) {
		if (!extension_loaded('curl'))
		  throw new WebserviceException('Please activate the PHP extension \'curl\' to allow use of PrestaShop webservice library');
		$this->url = $url;
		$this->key = $key;
		$this->debug = $debug;
		$this->version = 'unknown';
	}

	/**
	 * Take the status code and throw an exception if the server didn't return 200 or 201 code
	 * @param int $status_code Status code of an HTTP return
	 */
	protected function checkStatusCode($status_code)
	{
		$error_label = 'This call to PrestaShop Web Services failed and returned an HTTP status of %d. That means: %s.';
		switch($status_code)
		{
			case 200:	case 201:	break;
			case 204: throw new WebserviceException(sprintf($error_label, $status_code, 'No content'));break;
			case 400: throw new WebserviceException(sprintf($error_label, $status_code, 'Bad Request'));break;
			case 401: throw new WebserviceException(sprintf($error_label, $status_code, 'Unauthorized'));break;
			case 404: throw new WebserviceException(sprintf($error_label, $status_code, 'Not Found'));break;
			case 405: throw new WebserviceException(sprintf($error_label, $status_code, 'Method Not Allowed'));break;
			case 500: throw new WebserviceException(sprintf($error_label, $status_code, 'Internal Server Error'));break;
			default: throw new WebserviceException('This call to PrestaShop Web Services returned an unexpected HTTP status of:' . $status_code);
		}
	}
	/**
	 * Handles a CURL request to PrestaShop Webservice. Can throw exception.
	 * @param string $url Resource name
	 * @param mixed $curl_params CURL parameters (sent to curl_set_opt)
	 * @return array status_code, response
	 */
	protected function executeRequest($url, $curl_params = array())
	{
		$defaultParams = array(
			CURLOPT_HEADER => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLINFO_HEADER_OUT => TRUE,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERPWD => $this->key.':',
			CURLOPT_HTTPHEADER => array( 'Expect:' )
		);

		$session = curl_init($url);

		$curl_options = array();
		foreach ($defaultParams as $defkey => $defval)
		{
			if (isset($curl_params[$defkey]))
				$curl_options[$defkey] = $curl_params[$defkey];
			else
				$curl_options[$defkey] = $defaultParams[$defkey];
		}
		foreach ($curl_params as $defkey => $defval)
			if (!isset($curl_options[$defkey]))
				$curl_options[$defkey] = $curl_params[$defkey];

		curl_setopt_array($session, $curl_options);
		$response = curl_exec($session);

		$index = strpos($response, "\r\n\r\n");
		if ($index === false && $curl_params[CURLOPT_CUSTOMREQUEST] != 'HEAD')
			throw new WebserviceException('Bad HTTP response');

		$header = substr($response, 0, $index);
		$body = substr($response, $index + 4);

		$headerArrayTmp = explode("\n", $header);

		$headerArray = array();
		foreach ($headerArrayTmp as &$headerItem)
		{
			$tmp = explode(':', $headerItem);
			$tmp = array_map('trim', $tmp);
			if (count($tmp) == 2)
				$headerArray[$tmp[0]] = $tmp[1];
		}

		if (array_key_exists('PSWS-Version', $headerArray))
		{
			$this->version = $headerArray['PSWS-Version'];
			if (
				version_compare(static::PS_COMPATIBLE_VERSIONS_MIN, $headerArray['PSWS-Version']) == 1 ||
				version_compare(static::PS_COMPATIBLE_VERSIONS_MAX, $headerArray['PSWS-Version']) == -1
			)
			throw new WebserviceException('This library is not compatible with this version of PrestaShop. Please upgrade/downgrade this library');
		}

		if ($this->debug)
		{
			$this->printDebug('HTTP REQUEST HEADER', curl_getinfo($session, CURLINFO_HEADER_OUT));
			$this->printDebug('HTTP RESPONSE HEADER', $header);

		}
		$status_code = curl_getinfo($session, CURLINFO_HTTP_CODE);
		if ($status_code === 0)
			throw new WebserviceException('CURL Error: '.curl_error($session));
		curl_close($session);
		if ($this->debug)
		{
			if ($curl_params[CURLOPT_CUSTOMREQUEST] == 'PUT' || $curl_params[CURLOPT_CUSTOMREQUEST] == 'POST')
				$this->printDebug('XML SENT', urldecode($curl_params[CURLOPT_POSTFIELDS]));
			if ($curl_params[CURLOPT_CUSTOMREQUEST] != 'DELETE' && $curl_params[CURLOPT_CUSTOMREQUEST] != 'HEAD')
				$this->printDebug('RETURN HTTP BODY', $body);
		}
		return array('status_code' => $status_code, 'response' => $body, 'header' => $header);
	}

	public function printDebug($title, $content)
	{
		echo '<div style="display:table;background:#CCC;font-size:8pt;padding:7px"><h6 style="font-size:9pt;margin:0">'.$title.'</h6><pre>'.htmlentities($content).'</pre></div>';
	}

	public function getVersion()
	{
		return $this->version;
	}

	/**
	 * Load XML from string. Can throw exception
	 * @param string $response String from a CURL response
	 * @return SimpleXMLElement status_code, response
	 */
	protected function parseXML($response)
	{
		libxml_clear_errors();
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($response,'SimpleXMLElement', LIBXML_NOCDATA);
		if (libxml_get_errors())
		{
			$msg = var_export(libxml_get_errors(), true);
			libxml_clear_errors();
			throw new WebserviceException('HTTP XML response is not parsable: '.$msg);
		}
		return $xml;
	}

	/**
	 * Add (POST) a resource
	 * <p>Unique parameter must take : <br><br>
	 * 'resource' => Resource name<br>
	 * 'postXml' => Full XML string to add resource<br><br>
	 * Examples are given in the tutorial</p>
	 * @param array $options
	 * @return SimpleXMLElement status_code, response
	 */
	public function add($options)
	{
		if (!isset($options[static::OPTION_OUTPUT_FORMAT]))
		{
			$options[static::OPTION_OUTPUT_FORMAT] = static::OPTION_VALUE_OUTPUT_FORMAT_JSON;
		}

		if (isset($options['resource'], $options['postXml']) || isset($options['url'], $options['postXml']))
		{
			$url = (isset($options['resource']) ? $this->url.'/api/'.$options['resource'] : $options['url']);
			$xml = $options['postXml'];
			if (isset($options['id_shop']))
				$url .= '&id_shop='.$options['id_shop'];
			if (isset($options['id_group_shop']))
				$url .= '&id_group_shop='.$options['id_group_shop'];
		}
		else
			throw new WebserviceException('Bad parameters given');
		$request = self::executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $xml));

		self::checkStatusCode($request['status_code']);

		return $this->parseResponse($request['response'], $options[static::OPTION_OUTPUT_FORMAT]);
	}

	/**
 	 * Retrieve (GET) a resource
	 * <p>Unique parameter must take : <br><br>
	 * 'url' => Full URL for a GET request of Webservice (ex: http://mystore.com/api/customers/1/)<br>
	 * OR<br>
	 * 'resource' => Resource name,<br>
	 * 'id' => ID of a resource you want to get<br><br>
	 * </p>
	 * <code>
	 * <?php
	 * require_once('./PrestaShopWebservice.php');
	 * try
	 * {
	 * $ws = new PrestaShopWebservice('http://mystore.com/', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ', false);
	 * $xml = $ws->get(array('resource' => 'orders', 'id' => 1));
	 *	// Here in $xml, a SimpleXMLElement object you can parse
	 * foreach ($xml->children()->children() as $attName => $attValue)
	 * 	echo $attName.' = '.$attValue.'<br />';
	 * }
	 * catch (WebserviceException $ex)
	 * {
	 * 	echo 'Error : '.$ex->getMessage();
	 * }
	 * ?>
	 * </code>
	 * @param array $options Array representing resource to get.
	 * @return SimpleXMLElement status_code, response
	 */
	public function get($options)
	{
		if (!isset($options[static::OPTION_OUTPUT_FORMAT]))
		{
			$options[static::OPTION_OUTPUT_FORMAT] = static::OPTION_VALUE_OUTPUT_FORMAT_JSON;
		}

		if (isset($options[static::OPTION_URL]))
		{
			$url = $options[static::OPTION_URL];
		}
		elseif (isset($options[static::OPTION_RESOURCE]))
		{
			$url        = $this->url.'/api/'.$options[static::OPTION_RESOURCE];
			$url_params = array();

			if (isset($options[static::OPTION_ID]))
			{
				$url .= '/' . $options[static::OPTION_ID];
			}

			$params = array(
				static::OPTION_FILTER,
				static::OPTION_DISPLAY,
				static::OPTION_SORT,
				static::OPTION_LIMIT,
				static::OPTION_ID_SHOP,
				static::OPTION_ID_GROUP_SHOP,
				static::OPTION_OUTPUT_FORMAT
			);

			foreach ($params as $p)
				foreach ($options as $k => $o)
					if (strpos($k, $p) !== false)
						$url_params[$k] = $options[$k];
			if (count($url_params) > 0)
				$url .= '?'.http_build_query($url_params);
		}
		else
		{
			throw new WebserviceException('Bad parameters given');
		}

		$request = self::executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'GET'));

		self::checkStatusCode($request['status_code']);// check the response validity

		return $this->parseResponse($request['response'], $options[static::OPTION_OUTPUT_FORMAT]);
	}

	/**
 	 * Head method (HEAD) a resource
	 *
	 * @param array $options Array representing resource for head request.
	 * @return SimpleXMLElement status_code, response
	 */
	public function head($options)
	{
		if (!isset($options[static::OPTION_OUTPUT_FORMAT]))
		{
			$options[static::OPTION_OUTPUT_FORMAT] = static::OPTION_VALUE_OUTPUT_FORMAT_JSON;
		}

		if (isset($options['url']))
			$url = $options['url'];
		elseif (isset($options['resource']))
		{
			$url = $this->url.'/api/'.$options['resource'];
			$url_params = array();
			if (isset($options['id']))
				$url .= '/'.$options['id'];

			$params = array('filter', 'display', 'sort', 'limit');
			foreach ($params as $p)
				foreach ($options as $k => $o)
					if (strpos($k, $p) !== false)
						$url_params[$k] = $options[$k];
			if (count($url_params) > 0)
				$url .= '?'.http_build_query($url_params);
		}
		else
			throw new WebserviceException('Bad parameters given');
		$request = self::executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'HEAD', CURLOPT_NOBODY => true));
		self::checkStatusCode($request['status_code']);// check the response validity
		return $request['header'];
	}
	/**
	 * Edit (PUT) a resource
	 * <p>Unique parameter must take : <br><br>
	 * 'resource' => Resource name ,<br>
	 * 'id' => ID of a resource you want to edit,<br>
	 * 'putXml' => Modified XML string of a resource<br><br>
	 * Examples are given in the tutorial</p>
	 * @param array $options Array representing resource to edit.
	 */
	public function edit($options)
	{
		if (!isset($options[static::OPTION_OUTPUT_FORMAT]))
		{
			$options[static::OPTION_OUTPUT_FORMAT] = static::OPTION_VALUE_OUTPUT_FORMAT_JSON;
		}

		$xml = '';
		if (isset($options['url']))
			$url = $options['url'];
		elseif ((isset($options['resource'], $options['id']) || isset($options['url'])) && $options['putXml'])
		{
			$url = (isset($options['url']) ? $options['url'] : $this->url.'/api/'.$options['resource'].'/'.$options['id']);
			$xml = $options['putXml'];
			if (isset($options['id_shop']))
				$url .= '&id_shop='.$options['id_shop'];
			if (isset($options['id_group_shop']))
				$url .= '&id_group_shop='.$options['id_group_shop'];
		}
		else
			throw new WebserviceException('Bad parameters given');

		$request = self::executeRequest($url,  array(CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $xml));
		self::checkStatusCode($request['status_code']);// check the response validity
		return $this->parseResponse($request['response'], $options[static::OPTION_OUTPUT_FORMAT]);
	}

	/**
	 * Delete (DELETE) a resource.
	 * Unique parameter must take : <br><br>
	 * 'resource' => Resource name<br>
	 * 'id' => ID or array which contains IDs of a resource(s) you want to delete<br><br>
	 * <code>
	 * <?php
	 * require_once('./PrestaShopWebservice.php');
	 * try
	 * {
	 * $ws = new PrestaShopWebservice('http://mystore.com/', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ', false);
	 * $xml = $ws->delete(array('resource' => 'orders', 'id' => 1));
	 *	// Following code will not be executed if an exception is thrown.
	 * 	echo 'Successfully deleted.';
	 * }
	 * catch (WebserviceException $ex)
	 * {
	 * 	echo 'Error : '.$ex->getMessage();
	 * }
	 * ?>
	 * </code>
	 * @param array $options Array representing resource to delete.
	 */
	public function delete($options)
	{
		if (isset($options['url']))
			$url = $options['url'];
		elseif (isset($options['resource']) && isset($options['id']))
			if (is_array($options['id']))
				$url = $this->url.'/api/'.$options['resource'].'/?id=['.implode(',', $options['id']).']';
			else
				$url = $this->url.'/api/'.$options['resource'].'/'.$options['id'];
		if (isset($options['id_shop']))
			$url .= '&id_shop='.$options['id_shop'];
		if (isset($options['id_group_shop']))
			$url .= '&id_group_shop='.$options['id_group_shop'];
		$request = self::executeRequest($url, array(CURLOPT_CUSTOMREQUEST => 'DELETE'));
		self::checkStatusCode($request['status_code']);// check the response validity
		return true;
	}

	/**
	 * @param string $response
	 *
	 * @throws WebserviceException
	 *
	 * @return string
	 */
	protected function parseResponse($response, $format)
	{
		if ($response != '')
		{
			if ($format == static::OPTION_VALUE_OUTPUT_FORMAT_JSON)
			{
				return $response;
			}
			// XML
			else
			{
				return $this->parseXML($response);
			}
		}
		else
		{
			throw new WebserviceException('HTTP response is empty');
		}
	}
}
