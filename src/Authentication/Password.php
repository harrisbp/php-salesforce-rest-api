<?php
namespace Salesforce\Authentication;

use GuzzleHttp\Client;

class Password implements AuthenticationInterface 
{
    const ENDPOINT = 'https://login.salesforce.com/services/oauth2/token';
    const GRANT_TYPE = 'password';

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $clientSecret;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var array
     */
    protected $session;

    /**
     * Create a new Salesforce connection
     * @param string $clientId
     * @param string $secret
     * @param string $username
     * @param string $password
     */
    public function __construct($clientId, $clientSecret, $username, $password, $token) 
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->username = $username;
        $this->password = $password;
        $this->token = $token;
    }

    /**
     * Login to Salesforce
     * @return void
     */
    protected function login()
    {
        if(!isset($this->session))
        {
            $client = new Client();
            $response = $client->post(self::ENDPOINT, [
                'form_params' => [
                    'grant_type' => self::GRANT_TYPE,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'username' => $this->username,
                    'password' => $this->password . $this->token,
                ]
            ]);
            $this->session = json_decode($response->getBody());

            if($this->session === null) {
                $this->session = false;
                throw new \Exception("Unable to login to Salesforce.");
            }

            unset($client, $response);
        }
    }

    /**
     * Get the access token from the session
     * @return string|null
     */
    public function getAccessToken() 
    {
        $this->login();
        return ($this->session) ? $this->session->access_token : null;
    }

    /**
     * Get the instance URL from the session
     * @return string|null
     */
    public function getInstanceUrl() 
    {
        $this->login();
        return ($this->session) ? $this->session->instance_url : null;
    }
}