<?php
namespace Salesforce;

use GuzzleHttp\Client;
use Salesforce\Authentication\AuthenticationInterface;

class Salesforce 
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * @see Salesforce\Authentication\AuthenticationInterface
     */
    private $authentication;

    /**
     * Constructor
     * @param Salesforce\Authentication\AuthenticationInterface $authentication
     */
    private final function __construct(AuthenticationInterface $authentication) {
        $this->authentication = $authentication;
    }

    /**
     * Configure the Salesforce client
     *
     * @param Salesforce\Authentication\AuthenticationInterface $authentication
     * @return Salesforce\Salesforce
     */
    public static function configure(AuthenticationInterface $authentication) {
        if (null === static::$instance) {
            static::$instance = new static($authentication);
        }
        
        return static::$instance;
    }

    private function __clone() {}
    private function __wakeup() {}

}