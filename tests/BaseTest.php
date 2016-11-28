<?php
namespace Tests;

use Dotenv\Dotenv;
use Salesforce\Connection;
use Salesforce\Authentication\Password as PasswordAuthentication;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @see Dotenv\Dotenv
     */
    protected $config;

    /**
     * @see Salesforce\Connection
     */
    protected $connection;

    public function __construct()
    {
        parent::__construct();
        
        // Load configuration using the dotenv (.env)
        // We have included a sample .env file for your reference
        $this->config = new Dotenv(__DIR__);
        $this->config->load();

        // Ensure the .env file has been setup
        $this->config->required([
            'SALESFORCE_CLIENT_ID', 
            'SALESFORCE_CLIENT_SECRET', 
            'SALESFORCE_USERNAME', 
            'SALESFORCE_PASSWORD', 
            'SALESFORCE_TOKEN'
        ]);
    }

    /**
     * Get a Salesforce connection
     * @return Salesforce\Connection
     */
    protected function getConnection()
    {
        if(!isset($this->connection)) 
        {
            $this->connection = new Connection(
                new PasswordAuthentication(
                    getenv('SALESFORCE_CLIENT_ID'),
                    getenv('SALESFORCE_CLIENT_SECRET'),
                    getenv('SALESFORCE_USERNAME'),
                    getenv('SALESFORCE_PASSWORD'),
                    getenv('SALESFORCE_TOKEN')
                )
            );
        }

        return $this->connection;
    }

    /**
     * Bootstrap the library by setting up the connection and attempting a login
     * @return GuzzleHttp\Client
     */
    protected function bootstrap()
    {
        return $this->getConnection()->getHttpClient();
    }
}