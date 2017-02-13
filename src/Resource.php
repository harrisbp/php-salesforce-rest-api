<?php
namespace Salesforce;

use GuzzleHttp\Client as HttpClient;

abstract class Resource {
	use Cacheable;

	protected static $booted = [];

	/**
	 * Local cache of loaded metadata
	 *
	 * @var array
	 */
	protected static $metadata = [];

	/**
	 * HTTP Client Instance
	 * @see \GuzzleHttp\Client
	 */
	protected static $client;

	/**
	 * Original values
	 *
	 * @var array
	 */
	protected $original = [];

	/**
	 * Updated values
	 *
	 * @var array
	 */
	protected $attributes = [];

	/**
	 * Base URI of resource requests
	 * @var string
	 */
	protected static $baseUri = "sobjects/";

	/**
	 * Create a new fluent container instance.
	 *
	 * @param  array|object    $attributes
	 * @return void
	 */
	public function __construct($attributes = [], $pdo = null) {
		if (!is_null($pdo)) {
			self::$pdo = $pdo;
			self::$useCache = true;
		}

		$this->bootIfNotBooted();

		foreach ($attributes as $key => $value) {
			$this->set($key, $value);
		}

		$this->original = $this->attributes;
	}

	/**
	 * Check if the model needs to be booted and if so, do it.
	 *
	 * @return void
	 */
	protected function bootIfNotBooted() {
		if (!isset(static::$booted[static::class])) {
			static::boot();
			static::$booted[static::class] = true;
		}
	}

	protected static function boot() {
		if (self::$useCache) {
			$metadata = self::cacheGetMetadata(self::getResourceNameStatically());
		} else {
			$metadata = @json_decode(static::$client->get(static::getBaseUrl() . "describe")->getBody());
		}

		if (!$metadata) {
			throw new Exception('Unable to decode metadata for resource: ' . static::getResourceName());
		}

		static::$metadata[static::class] = new Metadata($metadata);
	}

	/**
	 * Get original describe of the object type
	 * @return array
	 */
	public function describe() {
		return $this->getMetadata()->describe();
	}

	/**
	 * Get the metadata for the current class resource
	 *
	 * @return \Salesforce\Metadata
	 */
	public static function getMetadata() {
		return static::$metadata[static::class];
	}

	protected function getResourceName() {
		return (new \ReflectionClass(get_called_class()))->getShortName();
	}

	protected static function getResourceNameStatically() {
		return (new \ReflectionClass(get_called_class()))->getShortName();
	}

	/**
	 * getResourceUrl
	 * @param  int $id
	 * @return string
	 */
	public function getResourceUrl($id) {
		return static::$baseUri . static::getResourceName() . "/{$id}";
	}

	/**
	 * getBaseUrl
	 * @return string
	 */
	public function getBaseUrl() {
		return static::$baseUri . static::getResourceName() . '/';
	}

	public static function getBaseUrlStatically() {
		return static::$baseUri . static::getResourceNameStatically() . '/';
	}

	/**
	 * Lookup a specific resource
	 *
	 * @param  string $id
	 * @param  bool $forceBypassCache
	 * @return \Salesforce\Resource|null
	 */
	public static function find($id, $forceBypassCache = false) {
		if (self::$useCache && !$forceBypassCache) {
			$attributes = self::cacheGetById(self::getResourceNameStatically(), $id);
		} else {
			$attributes = @json_decode(static::$client->get(static::getResourceUrl($id))->getBody());
		}

		// Returns a new regular instance of the child class using the found object
		return new static($attributes);
	}

	/**
	 * save
	 * @return string | false
	 */
	public function save() {
		$data = $this->asArray();

		unset($data['Id']);
		unset($data['BillingAddress']);

		// Updating or creating?
		if ($this->Id) {
			foreach ($data as $key => $value) {
				if (empty($this->getMetadata()->getField($key)->canUpdate)) {
					unset($data[$key]);
				}
			}

			$response = static::$client->patch(static::getResourceUrl($this->Id), ['body' => json_encode($data)]);
			return $response && $response->getStatusCode() == 204 ? $this->Id : false;
		} else {
			foreach ($data as $key => $value) {
				if (empty($this->getMetadata()->getField($key)->canCreate)) {
					unset($data[$key]);
				}
			}

			$response = static::$client->post(static::getBaseUrl(), ['body' => json_encode($data)]);

			if (!$response || $response->getStatusCode() != 201) {
				return false;
			}

			if (empty($response->getHeaders()['Location'][0])) {
				throw new Exception('Unexpected response from object creation');
			}

			$path = $response->getHeaders()['Location'][0];
			preg_match('#([^\/]*)$#', $path, $matches);

			if (empty($matches[1])) {
				throw new Exception('Unexpected response path format from object creation');
			}

			return $this->Id = $matches[1];
		}
	}

	/**
	 * Delete record
	 * @param  int $id
	 * @return bool
	 */
	public function delete() {
		$response = @static::$client->delete(static::getResourceUrl($this->Id));
		return $response && $response->getStatusCode() == 204;
	}

	/**
	 * Set the client instance.
	 *
	 * @param  \GuzzleHttp\Client $client
	 * @return void
	 */
	public static function setHttpClient(HttpClient $client) {
		static::$client = $client;
	}

	/**
	 * Returns the httpClient
	 * @return HttpClient
	 */
	public static function getHttpClient() {
		return static::$client;
	}

	/**
	 * Get an attribute from the container.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public function get($key, $default = null) {
		static::processKey($key);

		if (array_key_exists($key, $this->attributes)) {
			return $this->attributes[$key];
		}

		return is_callable($default) ? $default() : $default;
	}

	public function asArray() {
		$arr = [];

		$allFields = static::getMetadata()->getAllFields();

		foreach ($allFields as $field => $data) {
			if (!is_null($this->get($field))) {
				$arr[$data->name] = $this->get($field); // Using get instead of $value in case there will be mutators
			}
		}

		return $arr;
	}

	public function fieldList() {
		$arr = [];

		$allFields = static::getMetadata()->getAllFields();

		foreach ($allFields as $field => $data) {
			$arr[] = $data->name;
		}

		return $arr;
	}

	/**
	 * Set an attribute from the container.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return mixed
	 */
	public function set($key, $value) {
		static::processKey($key);

		$this->attributes[$key] = $value;
		return $this;
	}

	public static function processKey(&$key) {
		return $key = mb_strtolower($key, 'UTF-8');
	}

	/**
	 * Get the attributes from the container.
	 *
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 * Convert the Fluent instance to an array.
	 *
	 * @return array
	 */
	public function toArray() {
		return $this->attributes;
	}

	/**
	 * Handle dynamic calls to the container to set attributes.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return $this
	 */
	public function __call($method, $parameters) {

		if (substr($method, 0, 3) == 'set') {
			return $this->{substr($method, 3)} = current($parameters);
		} else if (substr($method, 0, 3) == 'get') {
			return $this->get(substr($method, 3), current($parameters));
		}

		throw new Exception('Called unknown method: ' . $method);
	}

	/**
	 * Dynamically retrieve the value of an attribute.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key) {
		return $this->get($key);
	}

	/**
	 * Dynamically set the value of an attribute.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function __set($key, $value) {
		return $this->set($key, $value);
	}

	/**
	 * Dynamically check if an attribute is set.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function __isset($key) {
		return isset($this->attributes[$key]);
	}

	/**
	 * Dynamically unset an attribute.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function __unset($key) {
		unset($this->attributes[$key]);
	}
}