<?php
namespace Tests;
use Salesforce\Client;
use Salesforce\Resource\Account;

class AccountTest extends BaseTest
{
    public function testCan()
    {
        $this->bootstrap();

        $account = new Account();

        echo "<pre>";
        print_r($account);
        exit;

        $this->assertInstanceOf(Account::class, $account);
    }
}