<?php
namespace Salesforce\Authentication;

interface AuthenticationInterface 
{
    /**
     * Return the access token for the authentication method
     * @return string
     */
    public function getAccessToken();
}