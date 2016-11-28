<?php
namespace Salesforce;

use GuzzleHttp\Client as HttpClient;

abstract class Resource
{
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
	public function __construct($attributes = []) {
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
			static::$booted[static::class] = true;
			static::boot();
		}
	}

	protected static function boot() {
		$resourceName = (new \ReflectionClass(get_called_class()))->getShortName();
		$metadata = @json_decode(self::$client->get(self::$baseUri . $resourceName . "/describe")->getBody());

		if (!$metadata) {
			throw new Exception('Unable to decode metadata for resource: ' . $resourceName);
		}

		self::$metadata[static::class] = new Metadata($metadata);
	}

	/**
	 * Get the metadata for the current class resource
	 * 
	 * @return Salesforce\Metadata
	 */
	public static function getMetadata() {
		return self::$metadata[static::class];
	}

	/**
	 * Lookup a specific resource
	 * 
	 * @param  string $id
	 * @return Salesforce\Resource|null
	 */
	public static function find($id) {

		$resourceName = (new \ReflectionClass(get_called_class()))->getShortName();
		$metadata = @json_decode(self::$client->get(self::$baseUri . $resourceName . "/{$id}")->getBody());

		return new static($metadata);

	}

	/**
     * Set the client instance.
     *
     * @param  \GuzzleHttp\Client $client
     * @return void
     */
    public static function setHttpClient(HttpClient $client)
    {
        static::$client = $client;
    }

	/**
	 * Get an attribute from the container.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public function get($key, $default = null) {
		if (array_key_exists($key, $this->attributes)) {
			return $this->attributes[$key];
		}

		return $default instanceof Closure ? $default() : $default;
	}

	/**
	 * Set an attribute from the container.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return mixed
	 */
	public function set($key, $value) {
		$this->attributes[$key] = $value;
		return $this;
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

		if(substr($method, 0, 3) == 'set') {
			return $this->{substr($method, 3)} = current($parameters);
		}
		else if(substr($method, 0, 3) == 'get') {
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