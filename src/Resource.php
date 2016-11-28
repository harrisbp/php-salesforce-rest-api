<?php
namespace Salesforce;

abstract class Resource implements ArrayAccess {
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
			$this->attributes[$key] = $value;
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
		$metadata = @json_decode(self::$client->get(self::$baseUri . $resourceName)->getBody());

		if (!$metadata) {
			throw new Exception('Unable to decode metadata for resource: ' . $resourceName);
		}

		self::$metadata[static::class] = $metadata;

		if (count($metadata->fields)) {
			$attributes = [];
			foreach ($metadata->fields as $field) {
				$attributes[$field['name']] = [

				];
			}
		}
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

		return value($default);
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
	 * Convert the object into something JSON serializable.
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		return $this->toArray();
	}

	/**
	 * Convert the Fluent instance to JSON.
	 *
	 * @param  int  $options
	 * @return string
	 */
	public function toJson($options = 0) {
		return json_encode($this->jsonSerialize(), $options);
	}

	/**
	 * Determine if the given offset exists.
	 *
	 * @param  string  $offset
	 * @return bool
	 */
	public function offsetExists($offset) {
		return isset($this->{$offset});
	}

	/**
	 * Get the value for a given offset.
	 *
	 * @param  string  $offset
	 * @return mixed
	 */
	public function offsetGet($offset) {
		return $this->{$offset};
	}

	/**
	 * Set the value at the given offset.
	 *
	 * @param  string  $offset
	 * @param  mixed   $value
	 * @return void
	 */
	public function offsetSet($offset, $value) {
		$this->{$offset} = $value;
	}

	/**
	 * Unset the value at the given offset.
	 *
	 * @param  string  $offset
	 * @return void
	 */
	public function offsetUnset($offset) {
		unset($this->{$offset});
	}

	/**
	 * Handle dynamic calls to the container to set attributes.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return $this
	 */
	public function __call($method, $parameters) {
		$this->attributes[$method] = count($parameters) > 0 ? $parameters[0] : true;

		return $this;
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
		$this->attributes[$key] = $value;
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