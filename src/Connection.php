<?php
namespace Salesforce;

use Salesforce\Version;
use Salesforce\Authentication\AuthenticationInterface;
use GuzzleHttp\Client as HttpClient;

class Connection {

    /**
     * Array of Salesforce instance configuration options
     * which are set after logging into the 
     * @see Salesforce\Authentication\AuthenticationInterface
     */
    protected $authentication;

    protected $httpClient;
    protected $version;

    /**
     * Constructor
     * @param Salesforce\Authentication\AuthenticationInterface $authentication
     */
    public function __construct(AuthenticationInterface $authentication, $version = Version::DEFAULT_VERSION)
    {
        $this->authentication = $authentication;
        $this->version = $version;
    }

    public function getHttpClient($handler = null)
    {
        if(empty($this->httpClient)) {
            $this->initializeHttpClient($handler);
        }

        return $this->httpClient;
    }

    /**
     * Initialize the HTTP client
     * @return GuzzleHttp\Client
     */
    protected function initializeHttpClient($handler = null)
    {
        $params = [
            'base_uri' => "{$this->authentication->getInstanceUrl()}/services/data/{$this->version}/",
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authentication->getAccessToken(),
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ];

        if ($handler) {
            $params['handler'] = $handler;
        }

        $this->httpClient = new HttpClient($params);

        Resource::setHttpClient($this->httpClient);
        Query::setHttpClient($this->httpClient);
        Search::setHttpClient($this->httpClient);
    }

}