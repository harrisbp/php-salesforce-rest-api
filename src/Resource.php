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
     * Base URI of resource requests
     * @var string
     */
    protected static $baseUri = "sobjects/";

    public function __construct()
    {
        $this->bootIfNotBooted();
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if(!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            static::boot();
        }
    }

    protected static function boot()
    {
        $resourceName = (new \ReflectionClass(get_called_class()))->getShortName();
        $metadata = json_decode(self::$client->get(self::$baseUri . $resourceName)->getBody());
        self::$metadata[static::class] = $metadata;
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

}