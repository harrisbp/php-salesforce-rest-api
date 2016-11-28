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

    protected $sObjects = [
        'account'       => Object\Account::class,
        'contact'       => Object\Contact::class,
        'lead'          => Object\Lead::class,
        'opportunity'   => Object\Opportunity::class,
        'order'         => Object\Order::class,
    ];

    /**
     * Constructor
     * @param Salesforce\Authentication\AuthenticationInterface $authentication
     */
    public function __construct(AuthenticationInterface $authentication, $version = Version::DEFAULT_VERSION)
    {
        $this->authentication = $authentication;
        $this->version = $version;
    }

    public function getHttpClient()
    {
        if(!isset($this->httpClient)) {
            $this->httpClient = $this->initializeHttpClient();
        }

        return $this->httpClient;
    }

    /**
     * Initialize the HTTP client
     * @return GuzzleHttp\Client
     */
    protected function initializeHttpClient() 
    {
        $this->httpClient = new HttpClient([
            'base_uri' => "{$this->authentication->getInstanceUrl()}/services/data/{$this->version}/",
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authentication->getAccessToken(),
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);

        Resource::setHttpClient($this->httpClient);
    }

    /**
     * Load object metadata
     * @return stdClass
     */
    public function describeObject($object)
    {
        $objectName = ucwords(array_search($object, $this->sObjects));
        echo $objectName;
        exit;
        return $this->buildJsonResponse($this->getHttpClient()->get("sobjects/{$objectName}/describe"));
    }

    protected function buildJsonResponse($response)
    {
        return json_decode($response->getBody());
    }

    // /**
    //  * Dynamically retrieve various resource, attributes, etc from the client.
    //  *
    //  * @param  string $name
    //  * @return mixed
    //  */
    // public function __get($name) {
    //     //$this->loadSalesforceObjects();
    //     if(array_key_exists($name, $this->sObjects)) {
    //         return new $this->sObjects[$name]($this);
    //     }
    // }

}