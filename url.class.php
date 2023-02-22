<?php defined('SECURE') or die('Hello World!');
/**
 * Class URL
 * 
 * Another friendly URL class inspired by
 * https://github.com/Matheus2212/php-friendly-urls-class/
 * 
 * Provides methods to parse and manipulate URLs in a friendly format.
 *
 * @author CloudStudio
 * @version 1.0 unfinished
 * @license MIT
 */
class URL
{
	public static $data = [
		'site' => null,
		'domain' => null,
		'action' => null,
		'callback' => null,
		'current' => null,
		'slugs' => null,
		'filters' => [],
		'ignoreGet' => true,
		'method' => null,
		'idFound' => null,
	];

	/**
	 * Initialize URL parameters
	 * @param string $url the URL
	 * @param bool $ignoreGet ignore $_GET or not 
	 */
	public static function init($url = null, $ignoreGet = true)
	{
		// Filter input
		$url = self::filter($url);
		$uri = self::filterServer('REQUEST_URI');
		$scheme = self::filterServer('REQUEST_SCHEME');
		$host = self::filterServer('HTTP_HOST');
		$method = self::filterServer('REQUEST_METHOD');

		// Set $_GET
		self::set('ignoreGet', $ignoreGet);
		// Set Site
		self::set('site', $host);

		// Set URL from param or detect it later
		if (! empty($url)) {
			$webURL = rtrim($url, '/') . '/';
		}
		else {
			// Placeholder of domain name for checking, it must be wrong
			$url = 'Whatever! this is just for fun';
		}

		// Detect current URL
		$currentURL = rtrim("$scheme://$host$uri", '/') . '/';

		// Check if the given URL is invalid and doesn't match currentURL
		if (strpos($url, $currentURL) === false) {
			// Use URL detection for domain name
			$webURL = rtrim("$scheme://$host", '/') . '/';
		}

		// Set domain name to data
		self::set('domain', $webURL);

		// Set current URL to data
		self::set('current', $currentURL);

		// SET SLUGS FROM URL
		// Remove the base URL from the current URL
		$slugs = str_replace($webURL, '', $currentURL);

		// Split the URL into an array of individual slugs
		$slugs = explode('/', $slugs);

		// Remove empty slugs from array
		$slugs = array_filter($slugs);

		if (empty($slugs)) {
			// If there are no slugs in the URL
			$slugs = [];
		}

		// If you consider to using $_GET
		elseif (! $ignoreGet) {
			// If the slugs parameter is a string, it means it contains a '?' character, and we split it by '?' character to get the query string parameters.
			if (is_string($slugs)) {
				$slugs = explode('?', $slugs);
				// If there is a query string parameter, we split it again to get the individual key-value pairs and store them in $slugs array.
				if (isset($slugs[1])) {
					$slugs = explode('&', $slugs[1]);
				} else {
					$slugs = [];
				}
			}
			// If the slugs parameter is an array, we convert it to a string, and then split it by '?' character to get the query string parameters.
			else {
				$url = implode('?', $slugs);
				$slugs = explode('?', $url);
				// If there is a query string parameter, we split it again to get the individual key-value pairs and store them in $slugs array.
				if (isset($slugs[1])) {
					$slugs = explode('&', $slugs[1]);
				} else {
					$slugs = [];
				}
			}
		} 

		// Set action or controller from first slug
		$action = isset($slugs[0]) ? $slugs[0] : '';
		self::set('action', $action);

		// Find callback for controller method from second slug
		$callback = 'init';
		if (isset($slugs[1])) {
			$callback = $slugs[1];
		}

		// Set callback to data
		self::set('callback', $callback);

		// Set array of slugs
		self::set('slugs', $slugs);

		// Find ID from end of URL
		$id = self::findId();
		self::set('idfound', $id);

		// Set METHOD used for current request
		self::set('method', $method);

		// End of URL initialization
		return true;
	}

	/**
	 * Check if the current action exist in specified filters
	 * set to error404 if doesn't exists
	 * @param array $customFilters (Optional) Additional filters to add to the existing filters
	 * @param string $defaultAction the default action
	 * @return void
	 */
	public static function setAction($customFilters = array(), $defaultAction = 'error404')
	{
		// Get the action from the data
		$action = self::$data['action'];

		// Get the filters from the data
		$filters = &self::$data['filters'];

		// Add any additional filters and sanitize them
		foreach ($customFilters as $key => $value) {
			if (is_array($value)) {
				foreach ($value as &$subValue) {
					$subValue = self::filter($subValue);
				}
			} else {
				$value = self::filter($value);
			}
			$customFilters[$key] = self::filter($value);
		}

		$filters = array_merge($filters	, $customFilters);

		// Check if the action is in the filters
		if (!in_array($action, $filters)) {
			// If the action is not in the filters, set it to defaultAction or error page
			self::set('action', $defaultAction);
		}
	}

	/**
	 * Set URL data by key
	 * @param	string $key
	 * @param	string|array variadic $values
	 * @return	mixed
	 */
	public static function set($key, ...$values): bool
	{
		$key = self::filter($key);

		// Get reference from $data
		$data = &self::$data;

		// Filter the value from input
		foreach ($values as &$value) {
			$value = self::filter($value);
		}

		// Only store data if there is same key
		if (!array_key_exists(strtolower($key), array_change_key_case($data))) {
			return false;
		}

		switch ($key) {
			case 'ignoreget':
				$data['ignoreGet'] = $values[0];
				break;
			case 'filters':
				$data['filters'] = $values[0];
				break;
			case 'idfound':
				$data['idFound'] = $values[0];
				break;
			
			default:
				if (count($values) > 1) {
					$data[$key] = $values;
				} else {
					$data[$key] = $values[0];
				}
				break;
		}
		return true;
	}

	/**
	 * Get URL data by key
	 * @param	string $key
	 * @param	string|array $args - optional
	 * @return	mixed
	 */
	public static function get($key = '', $args = '')
	{
		if (empty($key)) {
			return self::$data;
		}

		// Convert key to lowercase
		$key = strtolower($key);

		switch ($key) {
			case 'site':
				return self::$data['site'];
			case 'slug':
				return self::getSlug($args);
			case 'slugs':
				return self::getSlugs();
			case 'current':
				return self::$data['current'];
			case 'id':
				return self::getID($args);
			case 'after':
				return self::getAfter($args);
			case 'urlafter':
				if (is_array($args) && count($args) > 1) {
					return self::getURLAfter($args[0], $args[1]);
				}
				return self::getURLAfter($args);
			default:
				// Change the key in self::$data to lowercase before checking
				$dataLowercase = array_change_key_case(self::$data, CASE_LOWER);
				if (array_key_exists($key, $dataLowercase)) {
					return $dataLowercase[$key];
				}
			break;
		}
	}

	/**
	 * To get domain name
	 * @param bool $returnIP return IP or name
	 * @return array the slugs array
	 */
	public static function domain($returnIP = false)
	{
		$domain		= self::$data['domain'];
		$addr		= self::filterServer('SERVER_ADDR');
		$serverIP 	= ($addr == '::1' ? '127.0.0.1' : $addr);
		if ($returnIP) {
			$domain = str_replace('localhost', $serverIP, $domain);
		}
		return $domain;
	}

	/**
	 * to get the slugs
	 * @return array the slugs array
	 */
	public static function getSlugs()
	{
		$formatted_slugs = array_map(fn ($slug) => "/$slug", self::$data['slugs']);
		return $formatted_slugs;
	}

	/**
	 * To get the slug
	 * @param string $slug
	 * @return string position of slug
	 */
	public static function getSlug($slug): string
	{
		// Find slug from array of slugs
		if (array_key_exists($slug, self::$data['slugs'])) {
			return '/' . self::$data['slugs'][$slug];
		}
		// Find slug from array of filters
		elseif (array_key_exists($slug, self::$data['filters'])) {
			return '/' . self::$data['filters'][$slug];
		}
		// Doesn't match anything
		else {
			return '/';
		}
	}

	/**
	 * To get the ID from url
	 * @param string $position to find ID on URL
	 * @return int the ID number
	 */
	public static function getID($position = null): int
	{
		if (!$position) {
			$position = count(self::$data['slugs']) - 1;
		}

		$slug = self::getSlug($position);

		if ($slug === '/') {
			return 0;
		}

		$parts = preg_split('/[-\/]/', $slug);
		$id = end($parts);

		return (int) $id;
	}

	/**
	 * Gets the ID in the URL before a parameter
	 * @param string $param Search parameters
	 * @return int|null ID found or null if not found
	 */
	public static function getIDBefore($param)
	{
		// Checks whether the parameter uses dot notation or not
		if (strpos($param, '.') !== false) {
			$parts = explode('.', $param);
			$param = end($parts);
		}

		$url_parts = explode('/', self::$data['current']);

		// Search for the position of the parameter in the URL
		$key = array_search($param, $url_parts);

		if ($key === false) {
			// Parameter not found in URL
			return null;
		}

		// Get the URL part before the parameter
		$url_parts = array_slice($url_parts, 0, $key);

		// Search for the ID in the last part of the URL
		$last_url_part = end($url_parts);
		$id_parts = explode('-', $last_url_part);
		$id = end($id_parts);

		return (int) $id;
	}

	/**
	 * Gets the ID in the URL after a parameter
	 * @param string $param Search parameters
	 * @return int|null ID found or null if not found
	 */
	public static function getIDAfter($param)
	{
		// Checks whether the parameter uses dot notation or not
		if (strpos($param, '.') !== false) {
			$parts = explode('.', $param);
			$param = end($parts);
		}

		$url_parts = explode('/', self::$data['current']);

		// Search for the position of the parameter in the URL
		$key = array_search($param, $url_parts);

		if ($key === false || !isset($url_parts[$key + 1])) {
			// Parameter not found in URL or ID missing after parameter
			return null;
		}

		// Gets the ID after the parameter
		$id_parts = explode('-', $url_parts[$key + 1]);
		$id = end($id_parts);

		return (int) $id;
	}


	/**
	 * Get the part of URL before the given parameter
	 *
	 * @param string $param The parameter to search for in URL
	 * @param string $separator the url separator
	 * @return string|array|null Returns the part of URL before the parameter, null if not found
	 * optional: you can use separator for returned values
	 */
	public static function getBefore(string $param, bool $separator = false)
	{
		$url_parts = explode('/', self::$data['current']);
		$key = array_search($param, $url_parts);
		if ($key === false || $key == 0) {
			return null;
		}
		$result = array_slice($url_parts, 0, $key);
		if ($separator) {
			return implode($separator, $result);
		}
		return end($result);
	}

	/**
	 * Retrieve the value in the URL after $param using dot notation
	 *
	 * @param string $param The part of the URL to look up
	 * @return mixed|null The value in the URL after $param
	 */
	public static function getAfter($param)
	{
		// Splits URL into parts separated by "/"
		$url_parts = explode('/', self::$data['current']);

		// Look up the index of $param in the URL
		$key = array_search($param, $url_parts);

		// If $param is not found or there is no value after $param, then return null
		if ($key === false || !isset($url_parts[$key + 1])) {
			return null;
		}

		// Gets the value after $param using dot notation
		$value = $url_parts[$key + 1];
		for ($i = $key + 2; $i < count($url_parts); $i++) {
			$value .= '.' . $url_parts[$i];
		}

		return $value;
	}

	/**
	 * Gets the part of the URL before the given parameter
	 *
	 * @param string $param reference to get the previous section
	 * @return string|null If found, or null if not found
	 */
	public static function getURLBefore(string $param): ?string
	{
		$current = self::$data['current'];
		$position = strpos($current, $param);

		if ($position === false) {
			return null;
		}

		return substr($current, 0, $position);
	}

	/**
	 * Gets the part of the URL after the given parameter
	 *
	 * @param string $param reference to get the next section
	 * @param bool $returnArray return as array or string
	 * @return string|null If found, or null if not found
	 */
	public static function getURLAfter($string, $returnArray = false)
	{
		// Get the current URL
		$url = self::$data['current'];

		// Find the position of the string in the URL
		$pos = strpos($url, $string);

		// If the string is not found, return null
		if ($pos === false) {
			return null;
		}

		// Get the substring after the string
		$substring = substr($url, $pos + strlen($string) + 1);

		// If the returnArray parameter is true, return the parts array
		if ($returnArray) {
			return explode('/', $substring);
		}

		// Otherwise, return string of rest of URL
		return $substring;
	}

	/**
	 * To find ID in last of URL
	 *
	 * @param string $input URL to filter
	 * @return string filtered string of URL
	 */
	public static function findId()
	{
		$slugs = self::getSlugs();
		$lastSlug = end($slugs);

		// check if id is in the last slug as integer
		if (is_numeric($lastSlug)) {
			return intval($lastSlug);
		}
		// check if id is in the last slug as string-integer format
		else {
			$slugParts = explode('-', $lastSlug);
			if (count($slugParts) > 1 && is_numeric(end($slugParts))) {
				return intval(end($slugParts));
			}
		}
	}

	/**
	 * Get the last word from the current URL
	 *
	 * @param bool $fromQuery Set to true to get the last word from the query string
	 * @param bool $ignoreHash Set to true to ignore hash in the URL
	 * @return string The last word of the URL
	 */
	public static function getLastWord($fromQuery = false, $ignoreHash = true)
	{
		// Get the current URL from the data
		$url = self::$data['current'];

		// Remove any query string from the URL if $fromQuery is not set to true
		if (!$fromQuery) {
			$url = strtok($url, '?');
		}

		// Remove hash from the URL if $ignoreHash is set to true
		if ($ignoreHash) {
			$url = strtok($url, '#');
		}

		// Explode the URL into an array using "/" as the delimiter
		$urlParts = explode('/', trim($url, '/'));

		// Get the last element of the URL
		$lastPart = end($urlParts);

		// If the last element is empty, get the second last element
		if (empty($lastPart)) {
			$lastPart = prev($urlParts);
		}

		// If the last part contains a query string, get the last word from it
		if ($fromQuery && strpos($lastPart, '=') !== false) {
			$query = parse_url($url, PHP_URL_QUERY);
			parse_str($query, $params);
			$lastPart = end($params);
		}

		// Return the last word
		return $lastPart;
	}


	/**
	 * To filter given URL or string
	 *
	 * @param string $input URL to filter
	 * @return string filtered string of URL
	 */
	private static function filter($input)
	{
		// if input is URL
		if ($input !== false && filter_var($input, FILTER_VALIDATE_URL)) {
			// Sanitize URL
			$url = filter_var($input, FILTER_SANITIZE_URL);

			// Convert to lowercase
			$url = strtolower($url);

			// Normalize URL
			$url = self::normalize($url);

			// Remove unwanted characters
			$url = preg_replace('/[^a-zA-Z0-9$\-_.+!*();:@=&%20\/-]/', '', $url);
			
			// Convert space to (-)
			$url = str_replace(' ', '-', $url);

			return $url;
		}

		// if input is not an array
		if (!is_array($input) && is_string($input)) {
			// Convert to lowercase
			$input = strtolower($input);

			// Normalize string
			$input = self::normalize($input);

			// if input is string escape html special chars
			$input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			return $input;
		}
		// if input is an array
		elseif (is_array($input)) {
			// loop through input
			foreach ($input as &$str) {
				// Escape html special chars
				if (is_string($str)) {
					// Convert to lowercase
					$str = strtolower($str);

					// Normalize string
					$str = self::normalize($str);

					$str = htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				}
			}
			return $input;
		}
		// process bool & null values
		elseif (is_bool($input) || is_null($input)) {
			return $input;
		}
		// may be its integer input?
		elseif (is_int($input)) {
			return $input;
		}
		// What the heck type of this input?
		else {
			return null;
		}
	}


	/**
	 * To filter $_SERVER variable
	 *
	 * @param string $var VARIABLE const to filter
	 * @return string filtered string of VARIABLE
	 */
	private static function filterServer($var)
	{
		if (isset($_SERVER[$var])) {
			$var = filter_input(INPUT_SERVER, $var, FILTER_SANITIZE_URL);
		}

		// If input is URL
		if ($var !== false && filter_var($var, FILTER_VALIDATE_URL)) {

			// Normalize string of URL
			$url = self::normalize($var);

			// Sanitize URL
			$url = filter_var($url, FILTER_SANITIZE_URL);

			// Convert space to (-)
			$url = str_replace(' ', '-', $url);

			// Convert to lowercase
			$url = strtolower($url);

			return $url;
		}

		// Normalize chars
		$var = self::normalize($var);

		// if input is string, escape html special chars
		$var = htmlspecialchars($var, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		// Convert space to (-)
		$var = str_replace(' ', '-', $var);

		// Convert to lowercase
		$var = strtolower($var);
		return $var;
	}

	private static function normalize($string)
	{
		if (is_string($string)) {
			$string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
			$string = preg_replace('/[^A-Za-z0-9\-~\/\\\?\:\@\#\[\]\=\&\+\$\,\.\_\!\*\'\(\)]/', '', $string); // allow certain characters in URL
			$string = strtr($string, array(
				' ' => '-', // replace space with dash
				'!' => '-', // replace exclamation with dash
				',' => '-', // replace comma with dash
				'\'' => '-', // replace quote with dash
				'+' => '-', // replace plus with dash
				'~' => '-', // replace tilde with dash
				'(' => '-', // replace bracket with dash
				')' => '-', // replace bracket with dash
				'[' => '-', // replace bracket with dash
				']' => '-', // replace bracket with dash
				'*' => '-', // replace Asterisk with dash
				'^' => '-', // replace Caret with dash
			));
			$string = preg_replace('/-+/', '-', $string); // remove duplicate dashes
			$string = trim($string, '-'); // trim dashes from beginning and end of string
			return $string;
		}
		return $string;
	}


}
