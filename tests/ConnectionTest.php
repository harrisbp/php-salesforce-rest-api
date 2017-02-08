<?php
namespace Tests;

use Salesforce\Connection;
use Salesforce\Authentication\Password as PasswordAuthentication;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client;

class ConnectionTest extends BaseTest
{
    public function testSuccessfulLogin()
    {
        $this->assertInstanceOf(Client::class, $this->getConnection()->getHttpClient());
    }

    public function testFailedLoginThrowsException()
    {
        return;

        try
        {
            $connection = new Connection(new PasswordAuthentication(
                'XXXXX',
                'XXXXX',
                'XXXXX',
                'XXXXX',
                'XXXXX'
            ));
            $connection->getHttpClient();
        }
        catch(ClientException $e) {
            $this->assertInstanceOf(ClientException::class, $e);
        }
    }
}